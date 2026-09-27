<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;

/**
 * SaaS'ta silinen tenant'ın Bagisto müşteri hesabını kaldırır. Tenant kaydı artık
 * soft-deleted olduğu için PushTenantToBagisto gibi handle()'da yeniden okunmaz;
 * eşleşme anahtarı olan owner e-postası dispatch anında alınır.
 *
 * Bagisto aktif (bekleyen/işlenen) siparişi olan hesabı silmez, askıya alır
 * (Bagisto Admin'in müşteri silme kuralıyla aynı) — bkz. Bagisto
 * `Webkul\SaasSync\Services\TenantCustomerSync::delete()`.
 */
class DeleteTenantFromBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(
        protected string $email,
    ) {}

    public function handle(BagistoSyncClient $client): void
    {
        $client->post('api/saas-sync/tenants', [
            'event' => 'deleted',
            'email' => $this->email,
        ]);
    }
}
