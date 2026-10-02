<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Http\Requests\RejectTenantApplicationRequest;
use Modules\Tenant\Http\Requests\StoreTenantApplicationRequest;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;
use Modules\Tenant\Models\TenantType;
use Modules\Tenant\Services\TenantApplicationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantApplicationController extends Controller
{
    /** Sayfa içinde önizlenebilen belge türleri: saklanan dosya uzantısı => MIME. */
    public const PREVIEW_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(private TenantApplicationService $service) {}

    public function create(): Response
    {
        return Inertia::render('Tenant::TenantApplicationForm');
    }

    public function store(StoreTenantApplicationRequest $request): RedirectResponse
    {
        $data = $request->safe()->only([
            'name', 'legal_name', 'tax_number', 'tax_office',
            'contact_person', 'contact_email', 'contact_phone',
            'address', 'city', 'district', 'country', 'postal_code',
            'message',
        ]);

        $files = [
            TenantApplicationDocument::TYPE_TAX_CERTIFICATE        => $request->file('vergi_levhasi'),
            TenantApplicationDocument::TYPE_TRADE_REGISTRY_GAZETTE => $request->file('ticaret_sicil_gazetesi'),
            TenantApplicationDocument::TYPE_SIGNATURE_CIRCULAR     => $request->file('imza_sirkuleri'),
        ];

        $this->service->submit($data, $files, $request->ip());

        return redirect()->route('tenant-applications.create')
            ->with('success', 'Başvurunuz alındı. Belgeleriniz incelendikten sonra sizinle iletişime geçilecektir.');
    }

    public function index(Request $request): Response
    {
        $query = TenantApplication::query()->with(['documents', 'reviewer:id,name']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $applications = $query->orderByDesc('id')->get()->map(fn (TenantApplication $a) => [
            'id'               => $a->id,
            'name'             => $a->name,
            'legal_name'       => $a->legal_name,
            'tax_number'       => $a->tax_number,
            'tax_office'       => $a->tax_office,
            'contact_person'   => $a->contact_person,
            'contact_email'    => $a->contact_email,
            'contact_phone'    => $a->contact_phone,
            'address'          => $a->address,
            'city'             => $a->city,
            'district'         => $a->district,
            'message'          => $a->message,
            'status'           => $a->status,
            'rejection_reason' => $a->rejection_reason,
            'source'           => $a->source,
            'reference'        => $a->reference,
            'details'          => $a->details,
            'missing_documents'=> $a->missing_documents ?? [],
            'missing_note'     => $a->missing_note,
            'documentsRequestedAt' => $a->documents_requested_at?->format('Y-m-d H:i'),
            'documentsRemindedAt'  => $a->documents_reminded_at?->format('Y-m-d H:i'),
            'documentsSubmittedAt' => $a->documents_submitted_at?->format('Y-m-d H:i'),
            'invitationSentAt'     => $a->invitation_sent_at?->format('Y-m-d H:i'),
            'invitationExpiresAt'  => $a->invitation_expires_at?->format('Y-m-d H:i'),
            'invitationOpen'       => $a->invitationIsOpen(),
            // Davet e-postası ulaşmazsa admin bağlantıyı kopyalayıp bayiye kendisi iletebilir.
            'invitationUrl'        => $a->invitationIsOpen() ? $this->storefrontUrl('bayilik-basvurusu/uyelik/'.$a->invitation_token) : null,
            'statusUrl'            => $a->isFromPortal() && $a->public_token ? $this->storefrontUrl('bayilik-basvurusu/durum/'.$a->public_token) : null,
            'completedAt'          => $a->completed_at?->format('Y-m-d H:i'),
            // Yalnızca hesap açılışını bekleyenler için (her satırda sorgu atmamak adına).
            'suggestedCode'        => $a->status === TenantApplication::STATUS_DOCUMENTS_REVIEW && $a->isFromPortal() ? $this->service->suggestCode($a) : null,
            'reviewer'         => $a->reviewer?->name,
            'reviewedAt'       => optional($a->reviewed_at)->format('Y-m-d H:i'),
            'convertedTenantId'=> $a->converted_tenant_id,
            'documents'        => $a->documents->map(fn (TenantApplicationDocument $d) => [
                'id'   => $d->id,
                'type' => $d->type,
                'name' => $d->original_filename,
                // pdf | image | null (önizlenemez → yalnızca indirme)
                'kind' => match (self::PREVIEW_TYPES[strtolower(pathinfo($d->path, PATHINFO_EXTENSION))] ?? null) {
                    'application/pdf' => 'pdf',
                    'image/jpeg', 'image/png' => 'image',
                    default => null,
                },
                'size' => $d->size,
                'uploadedAt' => $d->created_at?->format('Y-m-d H:i'),
            ])->values(),
            'createdAt'        => $a->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Tenant::TenantApplications', [
            'applications' => $applications,
            'tenantTypes'  => TenantType::query()->orderBy('name')->get(['id', 'name']),
            'cariEnabled'  => $this->service->cariAccountEnabled(),
            'defaultDiscountRate' => Tenant::DEFAULT_DISCOUNT_RATE,
            'filters'      => ['status' => $request->query('status', '')],
        ]);
    }

    public function approve(TenantApplication $application): RedirectResponse
    {
        $this->service->approve($application, auth()->user());

        return back();
    }

    public function reject(RejectTenantApplicationRequest $request, TenantApplication $application): RedirectResponse
    {
        $this->service->reject($application, $request->user(), $request->validated('rejection_reason'));

        return back();
    }

    public function requestDocuments(Request $request, TenantApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'types'   => ['required', 'array', 'min:1'],
            'types.*' => ['string', Rule::in(TenantApplicationDocument::TYPES)],
            'note'    => ['required', 'string', 'max:2000'],
        ], [
            'types.required' => 'En az bir belge seçin.',
            'note.required'  => 'Başvuru sahibine gösterilecek açıklama zorunlu.',
        ]);

        $this->service->requestDocuments($application, $request->user(), $data['types'], $data['note']);

        return back();
    }

    /** Ön başvuru değerlendirildi + görüşme yapıldı → bayinin resmi belge adımı açılır. */
    public function openDocuments(Request $request, TenantApplication $application): RedirectResponse
    {
        $this->service->openDocumentStage($application, $request->user());

        return back();
    }

    /** Resmi belge adımında bekleyen bayiye hatırlatma e-postası. */
    public function remindDocuments(TenantApplication $application): RedirectResponse
    {
        $this->service->remindDocuments($application);

        return back();
    }

    /** Resmi evrak onayı: iş ortağı pasif açılır, bayiye üyelik tamamlama daveti gider. */
    public function openAccount(Request $request, TenantApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'code'              => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('tenants', 'code')],
            'tenant_type_id'    => ['nullable', 'integer', 'exists:tenant_types,id'],
            'credit_limit'      => ['nullable', 'numeric', 'min:0'],
            'discount_rate'     => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'code.regex'  => 'İş ortağı kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
            'code.unique' => 'Bu iş ortağı kodu kullanılıyor.',
        ]);

        $this->service->openAccount($application, $request->user(), $data);

        return back();
    }

    public function resendInvitation(TenantApplication $application): RedirectResponse
    {
        $this->service->resendInvitation($application);

        return back();
    }

    /**
     * Belgeyi sayfa içinde önizlemek için inline sunar. Yalnızca PDF ve görsel (başvuru formları
     * zaten pdf/jpg/png kabul ediyor); tür, istemcinin bildirdiği MIME yerine saklanan dosyanın
     * uzantısından belirlenir, nosniff ile tarayıcının başka türe yorumlaması engellenir.
     */
    public function previewDocument(TenantApplication $application, TenantApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->tenant_application_id === $application->id, 404);
        abort_unless(Storage::exists($document->path), 404);

        $mime = self::PREVIEW_TYPES[strtolower(pathinfo($document->path, PATHINFO_EXTENSION))] ?? null;
        abort_unless($mime !== null, 415);

        return Storage::response($document->path, $document->original_filename, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /** Başvurunun tüm ayrıntılarını içeren, dosyalamaya uygun yazdırılabilir sayfa. */
    public function print(TenantApplication $application): View
    {
        $application->load(['documents', 'reviewer:id,name', 'convertedTenant.type', 'convertedTenant.carrier']);

        return view('tenant::applications.print', [
            'application' => $application,
            'previewTypes' => self::PREVIEW_TYPES,
        ]);
    }

    public function downloadDocument(TenantApplication $application, TenantApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->tenant_application_id === $application->id, 404);
        abort_unless(Storage::exists($document->path), 404);

        return Storage::download($document->path, $document->original_filename);
    }

    private function storefrontUrl(string $path): ?string
    {
        $base = rtrim((string) config('bagisto.sync.base_url'), '/');

        return $base === '' ? null : $base.'/'.$path;
    }
}
