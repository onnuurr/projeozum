<?php

namespace Modules\Tenant\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
                'status'     => TenantApplication::STATUS_PENDING,
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
                    'type'                  => $type,
                    'path'                  => $path,
                    'original_filename'     => $file->getClientOriginalName(),
                    'mime_type'             => $file->getClientMimeType(),
                    'size'                  => $file->getSize() ?: 0,
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
            'status'      => TenantApplication::STATUS_APPROVED,
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
            'status'            => TenantApplication::STATUS_REJECTED,
            'rejection_reason'  => $reason,
            'reviewed_by'       => $reviewer->id,
            'reviewed_at'       => now(),
        ]);
    }
}
