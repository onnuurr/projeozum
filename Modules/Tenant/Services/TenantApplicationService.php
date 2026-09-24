<?php

namespace Modules\Tenant\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;

class TenantApplicationService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $files  type => yüklenen dosya (bkz. TenantApplicationDocument::TYPE_*)
     */
    public function submit(array $data, array $files, ?string $ip): TenantApplication
    {
        return DB::transaction(function () use ($data, $files, $ip) {
            $application = TenantApplication::create([
                ...$data,
                'status' => TenantApplication::STATUS_PENDING,
                'ip_address' => $ip,
            ]);

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

            return $application;
        });
    }

    public function approve(TenantApplication $application, User $reviewer): void
    {
        if ($application->status !== TenantApplication::STATUS_PENDING) {
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
        if ($application->status !== TenantApplication::STATUS_PENDING) {
            return;
        }

        $application->update([
            'status' => TenantApplication::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
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
}
