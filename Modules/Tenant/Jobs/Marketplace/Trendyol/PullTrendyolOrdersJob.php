<?php

namespace Modules\Tenant\Jobs\Marketplace\Trendyol;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Tenant\Jobs\Marketplace\RunsMarketplaceSyncLog;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\MarketplaceClientGateway;
use Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService;

class PullTrendyolOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RunsMarketplaceSyncLog;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [15, 60, 180];

    public function __construct(public int $tenantId, public ?string $sinceIso = null) {}

    public function middleware(): array
    {
        return [(new RateLimited('marketplace-trendyol'))];
    }

    public function handle(MarketplaceClientGateway $gateway, TenantMarketplaceSyncService $sync): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $client = $gateway->resolveClient($tenant, 'trendyol');

        $this->runSync($sync, $tenant, 'trendyol', 'pull_orders', function () use ($client, $sync, $tenant) {
            $since  = $this->sinceIso ? new DateTimeImmutable($this->sinceIso) : new DateTimeImmutable('-1 hour');
            $orders = $client->fetchOrders($since);

            $items = 0;
            foreach ($orders as $order) {
                foreach ($order->lines as $line) {
                    $sync->recordSale($tenant, $order, $line);
                    $items++;
                }
            }

            return ['items_processed' => $items];
        });
    }
}
