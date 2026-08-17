<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Bagisto\Mappers\TenantPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Tenant\Models\Tenant;

/**
 * SaaS'ta bir tenant aktif/pasif edildiğinde veya owner bilgileri değiştiğinde
 * Bagisto'daki karşılık gelen müşteri hesabını senkronlar. `Webkul\SaasSync\
 * Jobs\PushEventToSaas` (Bagisto tarafı) ile parite: aynı tries/backoff.
 *
 * Payload plaintext şifre içerebilir (yalnız activated + şifre bu çağrıda
 * belirlendiyse) — loglanmaz, sadece HTTP gövdesinde taşınır.
 */
class PushTenantToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    /**
     * @param  'activated'|'deactivated'  $event
     */
    public function __construct(
        protected int $tenantId,
        protected string $event,
        protected ?string $plainPassword = null,
    ) {}

    public function handle(TenantPayloadMapper $mapper, BagistoSyncClient $client): void
    {
        $tenant = Tenant::with('primaryUser')->find($this->tenantId);

        if (! $tenant) {
            Log::warning('BagistoSync: tenant bulunamadı, push atlandı.', [
                'tenant_id' => $this->tenantId,
                'event' => $this->event,
            ]);

            return;
        }

        $payload = $this->event === 'deactivated'
            ? $mapper->toDeactivatedPayload($tenant)
            : $mapper->toActivatedPayload($tenant, $this->plainPassword);

        if (! $payload) {
            Log::warning('BagistoSync: tenant\'ın owner hesabı yok, push atlandı.', [
                'tenant_id' => $this->tenantId,
                'event' => $this->event,
            ]);

            return;
        }

        $client->post('api/saas-sync/tenants', $payload);
    }
}
