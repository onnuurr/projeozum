<?php

namespace Modules\Tenant\Jobs\Marketplace\Trendyol;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Product\Models\Product;
use Modules\Tenant\Jobs\Marketplace\RunsMarketplaceSyncLog;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\MarketplaceClientGateway;
use Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService;
use Modules\Tenant\Services\Marketplace\TrendyolProductPayloadBuilder;

class SyncTrendyolProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RunsMarketplaceSyncLog;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [15, 60, 180];

    /** @param array<int,int> $productIds */
    public function __construct(public int $tenantId, public array $productIds) {}

    public function middleware(): array
    {
        return [(new RateLimited('marketplace-trendyol'))];
    }

    public function handle(
        MarketplaceClientGateway $gateway,
        TenantMarketplaceSyncService $sync,
        TrendyolProductPayloadBuilder $payloadBuilder,
    ): void {
        $tenant = Tenant::findOrFail($this->tenantId);
        $client = $gateway->resolveClient($tenant, 'trendyol');

        $this->runSync($sync, $tenant, 'trendyol', 'push_product', function () use ($client, $payloadBuilder) {
            $pushed = 0;
            foreach (Product::query()->whereIn('id', $this->productIds)->cursor() as $product) {
                $client->pushProduct($payloadBuilder->build($product));
                $pushed++;
            }

            return ['items_processed' => $pushed];
        });
    }
}
