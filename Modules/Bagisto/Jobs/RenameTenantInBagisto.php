<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;

/**
 * Owner e-postası değişince Bagisto müşteri hesabının e-postasını değiştirir (hesap,
 * siparişler, bakiye korunur). TenantService::update aynı kayıtta ardından
 * TenantActivated da atar; o push yeni e-postayla önce işlenip boş bir kopya hesap
 * açarsa Bagisto bu yarışı çözer — bkz. Bagisto
 * `Webkul\SaasSync\Services\TenantCustomerSync::changeEmail()`.
 */
class RenameTenantInBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(
        protected string $oldEmail,
        protected string $newEmail,
    ) {}

    public function handle(BagistoSyncClient $client): void
    {
        $client->post('api/saas-sync/tenants', [
            'event' => 'email_changed',
            'old_email' => $this->oldEmail,
            'email' => $this->newEmail,
        ]);
    }
}
