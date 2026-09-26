<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Tenant\Events\TenantApplicationStageChanged;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;

/**
 * Bayi portalı başvurusunun adım değişikliğini mağazaya iletir; başvuru sahibine e-postayı
 * Bagisto gönderir (Webkul\BayiPortal\Http\Controllers\Api\DealerApplicationWebhookController).
 * Üyelik daveti (hesap açıldı) buradan geçmez; onu SaaS gönderir (bkz. PushTenantApplicationStage).
 *
 * Kuyruğa yalnızca başvuru id'si ve adım yazılır; token/not handle() sırasında taze okunur
 * (davet yeniden üretildiyse eski bağlantı gönderilmez, gizli token kuyrukta saklanmaz).
 */
class PushTenantApplicationStageToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(
        protected int $applicationId,
        protected string $stage,
    ) {}

    public function handle(BagistoSyncClient $client): void
    {
        $application = TenantApplication::find($this->applicationId);

        if (! $application) {
            return;
        }

        $payload = [
            'event' => $this->stage,
            'reference' => $application->reference,
            'email' => $application->contact_email,
            'name' => $application->name,
            'contact_person' => $application->contact_person,
            'status_token' => $application->public_token,
            'dealer_model' => $application->details['dealer_model'] ?? null,
        ];

        $payload += match ($this->stage) {
            TenantApplicationStageChanged::MISSING_DOCUMENTS => [
                'note' => $application->missing_note,
                'documents' => collect($application->missing_documents ?? [])
                    ->map(fn ($type) => TenantApplicationDocument::LABELS[$type] ?? $type)
                    ->values()
                    ->all(),
            ],
            TenantApplicationStageChanged::REJECTED => ['reason' => $application->rejection_reason],
            default => [],
        };

        $client->post('api/saas-sync/dealer-applications', $payload);
    }
}
