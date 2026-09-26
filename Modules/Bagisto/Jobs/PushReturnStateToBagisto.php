<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoReturnService;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\OrderReturn;

/**
 * İadenin SaaS durumunu (karar, fatura kontrolü, para iadesi) Bagisto'ya iletir
 * (Webkul\BayiPortal ReturnStateWebhookController). Bagisto RMA durumunu buna göre
 * günceller, bayi portalda görür. Durum handle() anında taze okunur.
 */
class PushReturnStateToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(protected int $orderReturnId) {}

    public function handle(BagistoSyncClient $client, BagistoReturnService $returns): void
    {
        $return = OrderReturn::find($this->orderReturnId);

        if (! $return) {
            return;
        }

        $client->post('api/saas-sync/returns', $returns->statePayload($return));
    }
}
