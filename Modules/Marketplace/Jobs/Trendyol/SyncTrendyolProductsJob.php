<?php

namespace Modules\Marketplace\Jobs\Trendyol;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Services\MarketplaceServiceResolver;
use Throwable;

class SyncTrendyolProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [15, 60, 180];

    /** @param array<int,int> $productIds */
    public function __construct(public int $tenantId, public array $productIds) {}

    public function middleware(): array
    {
        return [(new RateLimited('marketplace-trendyol'))];
    }

    public function handle(MarketplaceServiceResolver $resolver): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $service = $resolver->for($tenant, 'trendyol');
        $log = $service->startSyncLog('push_product');

        try {
            $pushed = 0;
            foreach (Product::query()->whereIn('id', $this->productIds)->cursor() as $product) {
                $service->pushProduct($product);
                $pushed++;
            }
            $service->finishSyncLog($log, ['items_processed' => $pushed]);
        } catch (Throwable $e) {
            $service->failSyncLog($log, $e->getMessage());
            throw $e;
        }
    }
}
