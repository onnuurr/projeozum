<?php

namespace Modules\Tenant\Services;

use App\Contracts\PlatformSettings;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Tenant\Events\TenantApplicationStageChanged;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;
use Modules\Tenant\Notifications\TenantApplicationActivityNotification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Başvuru kuyruğu. İki kaynak var:
 * - web (/tenant-basvuru): tek adım — pending → approve → "İş ortağı oluştur" (Tenants formu).
 * - bagisto (bayi portalı): iki aşama —
 *   pending (ön başvuru) → openDocumentStage → documents_requested → submitOfficialDocuments →
 *   documents_review (⇄ missing_documents) → openAccount → approved + davet →
 *   completeInvitation → tenant aktif.
 */
class TenantApplicationService
{
    public function __construct(
        private TenantService $tenants,
        private TenantUserService $users,
        private PlatformSettings $settings,
    ) {}

    /**
     * Bayi portalında "Cari hesap ve bakiyeden ödeme" açık mı? Ayar Bagisto modülünün
     * 'bagisto_features' grubunda (StorefrontFeatureService); Tenant, Bagisto'ya bağımlı olmadığı
     * için kernel PlatformSettings üzerinden okunur. Varsayılan kapalı (Bagisto kataloğuyla aynı).
     */
    public function cariAccountEnabled(): bool
    {
        return (bool) ($this->settings->group('bagisto_features')['cari_account'] ?? false);
    }

    /**
     * Hesap açılışında önerilen iş ortağı kodu: BAYI-<başvuru no>. Elle açılmış bir iş ortağı aynı
     * kodu almışsa sonraki boş sonek önerilir (BAYI-0007-2 …); kesin tekillik unique kuralıyla korunur.
     */
    public function suggestCode(TenantApplication $application): string
    {
        $base = 'BAYI-'.str_pad((string) $application->id, 4, '0', STR_PAD_LEFT);
        $taken = Tenant::withTrashed()->where('code', 'like', $base.'%')->pluck('code')->all();

        $code = $base;
        for ($i = 2; in_array($code, $taken, true); $i++) {
            $code = "{$base}-{$i}";
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files  type => yüklenen dosya (bkz. TenantApplicationDocument::TYPE_*)
     */
    public function submit(array $data, array $files, ?string $ip, string $source = TenantApplication::SOURCE_WEB): TenantApplication
    {
        $application = DB::transaction(function () use ($data, $files, $ip, $source) {
            $application = TenantApplication::create([
                ...$data,
                'status' => TenantApplication::STATUS_PENDING,
                'ip_address' => $ip,
                'source' => $source,
                'public_token' => Str::random(48),
            ]);

            $this->storeDocuments($application, $files);

            return $application;
        });

        $this->notifyReviewers($application, TenantApplicationActivityNotification::NEW_APPLICATION);

        if ($application->isFromPortal()) {
            TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::RECEIVED);
        }

        return $application;
    }

    /**
     * @param  array<string, UploadedFile|null>  $files  type => dosya
     */
    private function storeDocuments(TenantApplication $application, array $files): void
    {
        foreach ($files as $type => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            // Hassas belge — 'public' değil, 'local' (private, storage/app/private) disk.
            // Aynı desen: Modules/Finance/Http/Controllers/SupplierInvoiceController.php
            $path = $file->store("tenant-applications/{$application->id}");

            TenantApplicationDocument::create([
                'tenant_application_id' => $application->id,
                'type' => $type,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
        }
    }

    /**
     * Ön başvuru değerlendirildi ve görüşme yapıldı: başvuru sahibinin durum sayfasında resmi belge
     * adımı (belgeler, banka, ticari beyan, sözleşme) açılır ve kendisine e-posta gider.
     */
    public function openDocumentStage(TenantApplication $application, User $reviewer): void
    {
        if (! $application->isFromPortal() || $application->status !== TenantApplication::STATUS_PENDING) {
            return;
        }

        $application->update([
            'status' => TenantApplication::STATUS_DOCUMENTS_REQUESTED,
            'documents_requested_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::DOCUMENTS_REQUESTED);
    }

    /**
     * Resmi belge adımı açık ama bayi belgeleri henüz göndermedi: hatırlatma e-postası gider.
     * Yanlışlıkla art arda gönderimi önlemek için REMINDER_COOLDOWN_MINUTES içinde ikincisi reddedilir.
     */
    public function remindDocuments(TenantApplication $application): void
    {
        if (! $application->isFromPortal() || $application->status !== TenantApplication::STATUS_DOCUMENTS_REQUESTED) {
            throw ValidationException::withMessages(['application' => 'Bu başvuru resmi belge beklemiyor.']);
        }

        if ($application->documents_reminded_at?->gt(now()->subMinutes(TenantApplication::REMINDER_COOLDOWN_MINUTES))) {
            throw ValidationException::withMessages(['application' => 'Hatırlatma az önce gönderildi; biraz sonra tekrar deneyin.']);
        }

        $application->update(['documents_reminded_at' => now()]);

        TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::DOCUMENTS_REMINDER);
    }

    /**
     * Başvuru sahibinin resmi belge adımını göndermesi: belgeler kaydedilir, adımın form alanları
     * (banka, ciro hedefi, ödeme tercihi, sözleşme onayı) details'e eklenir, evrak incelemesine düşer.
     *
     * @param  array<string, mixed>  $details  details'e birleştirilecek alanlar
     * @param  array<string, UploadedFile|null>  $files
     */
    public function submitOfficialDocuments(TenantApplication $application, array $details, ?string $message, array $files): TenantApplication
    {
        $application = DB::transaction(function () use ($application, $details, $message, $files) {
            $this->storeDocuments($application, $files);

            $merged = array_replace_recursive($application->details ?? [], $details);

            $application->update([
                'status' => TenantApplication::STATUS_DOCUMENTS_REVIEW,
                'details' => $merged,
                'message' => $message,
                'documents_submitted_at' => now(),
            ]);

            return $application->fresh('documents');
        });

        $this->notifyReviewers($application, TenantApplicationActivityNotification::DOCUMENTS_SUBMITTED);

        return $application;
    }

    /**
     * Admin "Eksik evrak iste" (resmi evrak incelemesinde): başvuru missing_documents'a geçer;
     * başvuru sahibi istenen belgeleri yükleyene kadar onay bekler (red yine de mümkündür).
     *
     * @param  array<int, string>  $types  TenantApplicationDocument::TYPE_* değerleri
     */
    public function requestDocuments(TenantApplication $application, User $reviewer, array $types, string $note): void
    {
        if (! in_array($application->status, [TenantApplication::STATUS_DOCUMENTS_REVIEW, TenantApplication::STATUS_MISSING_DOCUMENTS], true)) {
            return;
        }

        $application->update([
            'status' => TenantApplication::STATUS_MISSING_DOCUMENTS,
            'missing_documents' => array_values(array_unique($types)),
            'missing_note' => $note,
            'missing_requested_at' => now(),
            'reviewed_by' => $reviewer->id,
        ]);

        TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::MISSING_DOCUMENTS);
    }

    /**
     * Başvuru sahibinin eksik belgeleri yüklemesi. İstenen tiplerin tamamı geldiyse başvuru
     * tekrar evrak incelemesine döner. Eski belge kaydı silinmez (denetim izi).
     *
     * @param  array<string, UploadedFile|null>  $files
     */
    public function resubmitDocuments(TenantApplication $application, array $files): TenantApplication
    {
        $application = DB::transaction(function () use ($application, $files) {
            $this->storeDocuments($application, $files);

            $requested = $application->missing_documents ?? [];
            $uploaded = array_keys(array_filter($files, fn ($file) => $file instanceof UploadedFile));
            $remaining = array_values(array_diff($requested, $uploaded));

            $application->update($remaining === []
                ? ['status' => TenantApplication::STATUS_DOCUMENTS_REVIEW, 'missing_documents' => null, 'documents_submitted_at' => now()]
                : ['missing_documents' => $remaining]);

            return $application->fresh('documents');
        });

        if ($application->status === TenantApplication::STATUS_DOCUMENTS_REVIEW) {
            $this->notifyReviewers($application, TenantApplicationActivityNotification::DOCUMENTS_SUBMITTED);
        }

        return $application;
    }

    /** Yalnızca web kaynaklı başvurular; bayi portalı başvuruları openAccount ile onaylanır. */
    public function approve(TenantApplication $application, User $reviewer): void
    {
        if ($application->isFromPortal() || $application->status !== TenantApplication::STATUS_PENDING) {
            return;
        }

        $application->update([
            'status' => TenantApplication::STATUS_APPROVED,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(TenantApplication $application, User $reviewer, string $reason): void
    {
        $open = [
            TenantApplication::STATUS_PENDING,
            TenantApplication::STATUS_DOCUMENTS_REQUESTED,
            TenantApplication::STATUS_DOCUMENTS_REVIEW,
            TenantApplication::STATUS_MISSING_DOCUMENTS,
        ];

        if (! in_array($application->status, $open, true)) {
            return;
        }

        $application->update([
            'status' => TenantApplication::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        if ($application->isFromPortal()) {
            TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::REJECTED);
        }
    }

    /**
     * Resmi evrak onayı = gerçek hesap açılışı. Başvuru verisinden iş ortağı (tenant) ve owner
     * kullanıcısı PASİF açılır — Bagisto müşterisi, bayi davet bağlantısıyla parolasını ve eksik
     * bilgilerini (kargo anlaşması vb.) girince completeInvitation'da aktifleşir.
     *
     * @param  array{code: string, tenant_type_id?: int|null, credit_limit?: float|null, discount_rate?: float|null}  $account
     */
    public function openAccount(TenantApplication $application, User $reviewer, array $account): Tenant
    {
        if (! $application->isFromPortal() || $application->status !== TenantApplication::STATUS_DOCUMENTS_REVIEW) {
            throw ValidationException::withMessages(['application' => 'Bu başvuru hesap açılışına uygun durumda değil.']);
        }

        if (User::where('email', $application->contact_email)->exists()) {
            throw ValidationException::withMessages(['application' => "{$application->contact_email} adresiyle zaten bir kullanıcı hesabı var."]);
        }

        $tenant = DB::transaction(function () use ($application, $reviewer, $account) {
            // Boş bırakılan ticari koşullar gönderilmez → kolonların DB varsayılanları geçerli olur.
            $terms = array_filter(
                collect($account)->only(['tenant_type_id', 'credit_limit', 'discount_rate'])->all(),
                fn ($value) => $value !== null && $value !== '',
            );
            // Cari hesap kapalıyken kredi limiti anlamsız; formda da gösterilmiyor.
            if (! $this->cariAccountEnabled()) {
                unset($terms['credit_limit']);
            }

            $tenant = $this->tenants->create([
                ...$terms,
                'code' => $account['code'],
                'name' => $application->name,
                'legal_name' => $application->legal_name,
                'tax_number' => $application->tax_number,
                'tax_office' => $application->tax_office,
                'email' => $application->contact_email,
                'phone' => $application->contact_phone,
                'contact_person' => $application->contact_person,
                'contact_phone' => $application->contact_phone,
                'address' => $application->address,
                'city' => $application->city,
                'district' => $application->district,
                'country' => $application->country ?: 'TR',
                'postal_code' => $application->postal_code,
                'notes' => "Bayi portalı başvurusu #{$application->reference}",
                'is_active' => false,
                'created_by' => $reviewer->id,
                'owner_name' => $application->contact_person ?: $application->name,
                'owner_email' => $application->contact_email,
                // Bayiler SaaS portalına girmiyor; parola Bagisto'da belirlenir. Owner'a
                // kimsenin bilmediği rastgele bir parola verilir.
                'owner_password' => Str::random(40),
            ]);

            $application->update([
                'status' => TenantApplication::STATUS_APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'converted_tenant_id' => $tenant->id,
                'missing_documents' => null,
            ]);

            $this->issueInvitation($application);

            return $tenant;
        });

        TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::ACCOUNT_OPENED);

        return $tenant;
    }

    /** Süresi dolan ya da ulaşmayan davet için yeni bağlantı üretir ve yeniden gönderir. */
    public function resendInvitation(TenantApplication $application): void
    {
        if ($application->status !== TenantApplication::STATUS_APPROVED || $application->completed_at || ! $application->converted_tenant_id) {
            return;
        }

        $this->issueInvitation($application);

        TenantApplicationStageChanged::dispatch($application, TenantApplicationStageChanged::ACCOUNT_OPENED);
    }

    private function issueInvitation(TenantApplication $application): void
    {
        $application->update([
            'invitation_token' => Str::random(64),
            'invitation_sent_at' => now(),
            'invitation_expires_at' => now()->addDays(TenantApplication::INVITATION_TTL_DAYS),
        ]);
    }

    public function findOpenInvitation(string $token): ?TenantApplication
    {
        $application = TenantApplication::with('convertedTenant')
            ->where('invitation_token', $token)
            ->where('source', TenantApplication::SOURCE_BAGISTO)
            ->first();

        return $application?->invitationIsOpen() && $application->convertedTenant ? $application : null;
    }

    /**
     * Bayinin üyelik tamamlama formu: teyit ettiği iletişim/adres bilgileri ve kargo anlaşması
     * tenant'a yazılır, tenant aktifleşir (TenantActivated → Bagisto müşteri senkronu).
     * Parola burada işlenmez; Bagisto tarafında belirlenir.
     *
     * @param  array{contact_person: string, contact_phone: string, address: string, city: string, district: string, postal_code: string, shipping_agreement_type: string, carrier_id?: int|null, contract_version?: string|null, contract_annexes?: array<string, string>, shipping_terms_version?: string|null}  $data
     */
    public function completeInvitation(TenantApplication $application, array $data): Tenant
    {
        $tenant = DB::transaction(function () use ($application, $data) {
            /** @var Tenant $tenant */
            $tenant = $application->convertedTenant()->lockForUpdate()->firstOrFail();
            $platform = $data['shipping_agreement_type'] === 'platform';

            $tenant->update([
                'contact_person' => $data['contact_person'],
                'contact_phone' => $data['contact_phone'],
                'phone' => $data['contact_phone'],
                'address' => $data['address'],
                'city' => $data['city'],
                'district' => $data['district'],
                'postal_code' => $data['postal_code'],
                'shipping_agreement_type' => $data['shipping_agreement_type'],
                'carrier_id' => $platform ? null : $data['carrier_id'],
                'shipping_terms_accepted_at' => $platform ? now() : null,
            ]);

            if ($owner = $tenant->primaryUser) {
                $this->users->updateOwner($owner, ['name' => $data['contact_person'], 'email' => null, 'password' => null]);
            }

            $details = $application->details ?? [];
            $details['consents']['membership_contract_version'] = $data['contract_version'] ?? null;
            $details['consents']['membership_contract_annex_versions'] = $data['contract_annexes'] ?? [];
            $details['consents']['shipping_terms_version'] = $platform ? ($data['shipping_terms_version'] ?? null) : null;
            $details['consents']['membership_accepted_at'] = now()->toIso8601String();

            $application->update([
                'details' => $details,
                'completed_at' => now(),
                'invitation_token' => null,
            ]);

            return $tenant;
        });

        $this->tenants->activate($tenant);

        $this->notifyReviewers($application, TenantApplicationActivityNotification::MEMBERSHIP_COMPLETED);

        return $tenant->fresh(['primaryUser', 'carrier']);
    }

    /**
     * `/tenants/applications` inceleme kuyruğunda onaylanan bir başvurudan "Tenant
     * Oluştur" ile Tenants sayfasına gelindiğinde (?from_application=ID), başvuru
     * verilerini + yüklenen belgeleri "Yeni Tenant" formunu önceden doldurmak için
     * hazırlar. Zaten bir tenant'a dönüştürülmüş başvurular tekrar kullanılamaz.
     */
    public function prefillFor(mixed $id): ?array
    {
        if (! $id) {
            return null;
        }

        $application = TenantApplication::query()
            ->where('status', TenantApplication::STATUS_APPROVED)
            ->whereNull('converted_tenant_id')
            ->with('documents')
            ->find($id);

        if (! $application) {
            return null;
        }

        return [
            'id' => $application->id,
            'name' => $application->name,
            'legal_name' => $application->legal_name,
            'tax_number' => $application->tax_number,
            'tax_office' => $application->tax_office,
            'email' => $application->contact_email,
            'phone' => $application->contact_phone,
            'contact_person' => $application->contact_person,
            'contact_phone' => $application->contact_phone,
            'address' => $application->address,
            'city' => $application->city,
            'district' => $application->district,
            'country' => $application->country,
            'postal_code' => $application->postal_code,
            'documents' => $application->documents->map(fn ($d) => [
                'id' => $d->id,
                'type' => $d->type,
                'name' => $d->original_filename,
                'url' => route('tenant-applications.documents.download', [$application->id, $d->id]),
            ])->values(),
        ];
    }

    /** Onaylı ve henüz dönüştürülmemiş başvuruyu oluşturulan tenant'a bağlar. */
    public function markConverted(int $applicationId, Tenant $tenant): void
    {
        TenantApplication::query()
            ->where('id', $applicationId)
            ->where('status', TenantApplication::STATUS_APPROVED)
            ->whereNull('converted_tenant_id')
            ->update(['converted_tenant_id' => $tenant->id]);
    }

    /**
     * @param  TenantApplicationActivityNotification::*  $kind
     */
    private function notifyReviewers(TenantApplication $application, string $kind): void
    {
        try {
            $reviewers = User::permission('tenant-application.approve')->get();
        } catch (PermissionDoesNotExist) {
            // İzin henüz tanımlanmamış (boş kurulum/test) — bildirim yüzünden başvuru kaydı düşmesin.
            return;
        }

        foreach ($reviewers as $reviewer) {
            $reviewer->notify(new TenantApplicationActivityNotification($application, $kind));
        }
    }
}
