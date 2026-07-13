<?php

namespace Modules\Tenant\Jobs\Marketplace\Trendyol;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Marketplace\Services\Trendyol\TrendyolOrderMapper;
use Modules\Tenant\Jobs\Marketplace\RunsMarketplaceSyncLog;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\MarketplaceClientGateway;
use Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService;

class ProcessTrendyolWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RunsMarketplaceSyncLog;

    public int $tries = 3;

    public function __construct(public int $tenantId, public array $payload) {}

    public function handle(MarketplaceClientGateway $gateway, TenantMarketplaceSyncService $sync): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        // Webhook dispatch anından bu yana credential deaktive/silinmiş olabilir (kuyruk gecikmesi,
        // retry); resolveClient() sonucu kullanılmasa da is_active=true + firstOrFail() side-effect'i
        // burada tenant'ın hâlâ yetkili olduğunu garantiler.
        $gateway->resolveClient($tenant, 'trendyol');

        $this->runSync($sync, $tenant, 'trendyol', 'webhook', function () use ($sync, $tenant) {
            // Trendyol webhook payload tipi farklı olabilir (order-status-change vs.).
            // İlk teslimde sade: order DTO map'le ve recordSale çağır.
            if (! isset($this->payload['orderNumber']) && ! isset($this->payload['order'])) {
                return ['items_processed' => 0];
            }

            $orderPayload = $this->payload['order'] ?? $this->payload;
            $order = (new TrendyolOrderMapper())->fromArray($orderPayload);

            $count = 0;
            foreach ($order->lines as $line) {
                $sync->recordSale($tenant, $order, $line);
                $count++;
            }

            return ['items_processed' => $count];
        });
    }
}
