<?php

namespace Modules\Bagisto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Bagisto\Mappers\TenantPayloadMapper;
use Modules\Product\Models\Carrier;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;
use Modules\Tenant\Rules\ValidTaxNumber;
use Modules\Tenant\Services\TenantApplicationService;

/**
 * Bagisto bayi portalının (ozmbayi) "Bayilik Başvurusu" ekranları için köprü.
 * Başvurular SaaS'ın TenantApplication kuyruğuna düşer; inceleme SaaS admininde
 * (/tenants/applications) yapılır. Bagisto yalnızca formları ve durum ekranını sunar.
 *
 * Akış (bkz. TenantApplicationService): ön başvuru (store) → admin resmi belge aşamasını açar →
 * resmi belgeler (official) → evrak incelemesi (⇄ eksik evrak: documents) → admin hesabı açar →
 * üyelik tamamlama (invitation / complete).
 *
 * Auth: VerifyBagistoInboundToken (order webhook'uyla aynı Bearer token).
 * Başvuru sahibi girişsizdir; durum sayfası `public_token`, üyelik tamamlama `invitation_token` ile açılır.
 */
class TenantApplicationApiController extends Controller
{
    public function __construct(private TenantApplicationService $service) {}

    /** Ön başvuru: firma/yetkili + satış modeli ve kanallar. Belge istenmez. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['required', 'string', 'max:32', new ValidTaxNumber],
            'tax_office' => ['required', 'string', 'max:191'],
            'contact_person' => ['required', 'string', 'max:191'],
            'contact_email' => ['required', 'email', 'max:191'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'ip_address' => ['nullable', 'ip'],

            'details' => ['required', 'array'],
            'details.company_type' => ['required', Rule::in(['sahis', 'limited', 'anonim'])],
            'details.dealer_model' => ['required', Rule::in(['dropshipping', 'toptan', 'hibrit'])],
            'details.channels' => ['required', 'array', 'min:1'],
            'details.channels.*.type' => ['required', Rule::in(['fiziki', 'instagram', 'web', 'pazaryeri'])],
            'details.channels.*.value' => ['nullable', 'string', 'max:255'],
            'details.monthly_volume' => ['required', 'string', 'max:64'],
            'details.consents.declaration' => ['accepted'],
            'details.consents.marketing' => ['nullable', 'boolean'],
        ]);

        $details = collect($data['details'])->only(['company_type', 'dealer_model', 'channels', 'monthly_volume'])->all();
        $details['consents'] = [
            'declaration' => true,
            'marketing' => (bool) ($data['details']['consents']['marketing'] ?? false),
            'accepted_at' => now()->toIso8601String(),
        ];

        $application = $this->service->submit(
            [
                ...collect($data)->only([
                    'name', 'legal_name', 'tax_number', 'tax_office', 'contact_person', 'contact_email',
                    'contact_phone', 'address', 'city', 'district', 'postal_code',
                ])->all(),
                'country' => 'TR',
                'details' => $details,
            ],
            [],
            $data['ip_address'] ?? $request->ip(),
            TenantApplication::SOURCE_BAGISTO,
        );

        return response()->json(['data' => $this->present($application->fresh('documents'))], 201);
    }

    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->present($this->find($token))]);
    }

    /** Resmi belge adımı (görüşme sonrası admin açar): belgeler, banka, ticari beyan, sözleşme. */
    public function official(Request $request, string $token): JsonResponse
    {
        $application = $this->find($token);

        abort_unless($application->status === TenantApplication::STATUS_DOCUMENTS_REQUESTED, 409, 'Bu başvuru için resmi belge adımı açık değil.');

        $document = File::default()->max(10 * 1024)->extensions(['pdf', 'jpg', 'jpeg', 'png']);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'details' => ['required', 'array'],
            'details.revenue_target' => ['required', 'string', 'max:64'],
            'details.payment_preference' => ['required', Rule::in(['kart', 'havale'])],
            'details.bank.bank_name' => ['required', 'string', 'max:191'],
            'details.bank.account_holder' => ['required', 'string', 'max:191'],
            'details.bank.iban' => ['required', 'string', 'regex:/^TR\d{24}$/'],
            'details.consents.contract' => ['accepted'],
            'details.consents.contract_version' => ['nullable', 'string', 'max:32'],

            'vergi_levhasi' => ['required', $document],
            'imza_sirkuleri' => ['required', $document],
            'ticaret_sicil_gazetesi' => ['nullable', $document],
            'diger' => ['nullable', $document],
        ], [
            'details.bank.iban.regex' => 'IBAN TR ile başlayan 26 karakter olmalı.',
        ]);

        $details = [
            'revenue_target' => $data['details']['revenue_target'],
            'payment_preference' => $data['details']['payment_preference'],
            'bank' => [...$data['details']['bank'], 'iban' => strtoupper($data['details']['bank']['iban'])],
            'consents' => [
                'contract' => true,
                'contract_version' => $data['details']['consents']['contract_version'] ?? null,
                'contract_accepted_at' => now()->toIso8601String(),
            ],
        ];

        $application = $this->service->submitOfficialDocuments(
            $application,
            $details,
            $data['message'] ?? null,
            collect(TenantApplicationDocument::TYPES)->mapWithKeys(fn ($type) => [$type => $request->file($type)])->all(),
        );

        return response()->json(['data' => $this->present($application)]);
    }

    /** Evrak incelemesinde istenen eksik belgeler. */
    public function uploadDocuments(Request $request, string $token): JsonResponse
    {
        $application = $this->find($token);

        abort_unless($application->status === TenantApplication::STATUS_MISSING_DOCUMENTS, 409, 'Bu başvuru için bekleyen belge talebi yok.');

        $allowed = $application->missing_documents ?? [];

        $request->validate(
            collect($allowed)->mapWithKeys(fn ($type) => [$type => ['nullable', File::default()->max(10 * 1024)->extensions(['pdf', 'jpg', 'jpeg', 'png'])]])->all()
        );

        $files = collect($allowed)->mapWithKeys(fn ($type) => [$type => $request->file($type)])->filter()->all();

        abort_if($files === [], 422, 'En az bir istenen belgeyi yükleyin.');

        return response()->json(['data' => $this->present($this->service->resubmitDocuments($application, $files))]);
    }

    /** Üyelik tamamlama formunun ön dolgusu. Süresi dolmuş/kullanılmış bağlantıda 410. */
    public function invitation(string $token): JsonResponse
    {
        $application = $this->service->findOpenInvitation($token);

        abort_unless($application, 410, 'Bu üyelik bağlantısı geçersiz ya da süresi dolmuş.');

        $tenant = $application->convertedTenant;

        return response()->json(['data' => [
            'reference' => $application->reference,
            'name' => $tenant->name,
            'legal_name' => $tenant->legal_name,
            'tax_number' => $tenant->tax_number,
            'tax_office' => $tenant->tax_office,
            'email' => $application->contact_email,
            'contact_person' => $tenant->contact_person,
            'contact_phone' => $tenant->contact_phone,
            'address' => $tenant->address,
            'city' => $tenant->city,
            'district' => $tenant->district,
            'postal_code' => $tenant->postal_code,
            'dealer_model' => $application->details['dealer_model'] ?? null,
            'expires_at' => $application->invitation_expires_at?->toIso8601String(),
            'carriers' => Carrier::query()->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
        ]]);
    }

    /**
     * Üyeliği tamamlar ve Bagisto'nun müşteri hesabını hemen açabilmesi için tenant senkron
     * payload'ını döner (TenantActivated job'ı da aynı veriyi ayrıca gönderir; iki yol idempotent).
     * Parola SaaS'a gelmez; Bagisto kendi tarafında belirler.
     */
    public function complete(Request $request, TenantPayloadMapper $mapper, string $token): JsonResponse
    {
        $application = $this->service->findOpenInvitation($token);

        abort_unless($application, 410, 'Bu üyelik bağlantısı geçersiz ya da süresi dolmuş.');

        $data = $request->validate([
            'contact_person' => ['required', 'string', 'max:191'],
            'contact_phone' => ['required', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'shipping_agreement_type' => ['required', Rule::in(['own', 'platform'])],
            'carrier_id' => ['required_if:shipping_agreement_type,own', 'nullable', 'integer', Rule::exists('carriers', 'id')->where('is_active', true)],
            'shipping_terms_accepted' => ['accepted_if:shipping_agreement_type,platform'],
            'contract' => ['accepted'],
            'contract_version' => ['nullable', 'string', 'max:32'],
        ], [
            'postal_code.regex' => 'Posta kodu 5 haneli olmalı.',
            'carrier_id.required_if' => 'Kendi kargo anlaşmanızı kullanıyorsanız kargo firmasını seçin.',
            'shipping_terms_accepted.accepted_if' => 'Platform kargo anlaşması şartlarını onaylamalısınız.',
        ]);

        $tenant = $this->service->completeInvitation($application, $data);

        return response()->json(['data' => ['customer' => $mapper->toActivatedPayload($tenant, null)]]);
    }

    private function find(string $token): TenantApplication
    {
        return TenantApplication::with('documents')
            ->where('public_token', $token)
            ->where('source', TenantApplication::SOURCE_BAGISTO)
            ->firstOrFail();
    }

    /**
     * Başvuru sahibine gösterilecek alanlar (admin notları/değerlendiren kişi paylaşılmaz; IBAN maskelenir).
     */
    private function present(TenantApplication $application): array
    {
        $details = $application->details ?? [];

        if (! empty($details['bank']['iban'])) {
            $details['bank']['iban'] = substr($details['bank']['iban'], 0, 4).' **** **** '.substr($details['bank']['iban'], -4);
        }

        return [
            'token' => $application->public_token,
            'reference' => $application->reference,
            'status' => $application->status,
            'created_at' => $application->created_at?->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            'documents_requested_at' => $application->documents_requested_at?->toIso8601String(),
            'documents_submitted_at' => $application->documents_submitted_at?->toIso8601String(),
            'invitation_sent_at' => $application->invitation_sent_at?->toIso8601String(),
            'completed_at' => $application->completed_at?->toIso8601String(),
            'name' => $application->name,
            'legal_name' => $application->legal_name,
            'tax_number' => $application->tax_number,
            'tax_office' => $application->tax_office,
            'contact_person' => $application->contact_person,
            'contact_email' => $application->contact_email,
            'contact_phone' => $application->contact_phone,
            'address' => $application->address,
            'city' => $application->city,
            'district' => $application->district,
            'details' => $details,
            'message' => $application->message,
            'rejection_reason' => $application->status === TenantApplication::STATUS_REJECTED ? $application->rejection_reason : null,
            'missing_documents' => collect($application->missing_documents ?? [])->map(fn ($type) => [
                'type' => $type,
                'label' => TenantApplicationDocument::LABELS[$type] ?? $type,
            ])->values(),
            'missing_note' => $application->status === TenantApplication::STATUS_MISSING_DOCUMENTS ? $application->missing_note : null,
            'missing_requested_at' => $application->missing_requested_at?->toIso8601String(),
            'documents' => $application->documents->sortBy('created_at')->map(fn (TenantApplicationDocument $document) => [
                'type' => $document->type,
                'label' => TenantApplicationDocument::LABELS[$document->type] ?? $document->type,
                'name' => $document->original_filename,
                'size' => $document->size,
                'uploaded_at' => $document->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
