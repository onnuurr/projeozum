<?php

namespace Modules\Marketplace\Jobs\Trendyol;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Services\MarketplaceServiceResolver;
use Throwable;

class PullTrendyolOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;
    public array $backoff = [15, 60, 180];

    public function __construct(public int $tenantId, public ?string $sinceIso = null) {}

    public function middleware(): array
    {
        return [(new RateLimited('marketplace-trendyol'))];
    }

    public function handle(MarketplaceServiceResolver $resolver): void
    {
        $tenant  = Tenant::findOrFail($this->tenantId);
        $service = $resolver->for($tenant, 'trendyol');
        $log     = $service->startSyncLog('pull_orders');

        try {
            $since = $this->sinceIso ? new DateTimeImmutable($this->sinceIso) : new DateTimeImmutable('-1 hour');
            $orders = $service->fetchOrders($since);

            $items = 0;
            foreach ($orders as $order) {
                foreach ($order->lines as $line) {
                    $service->recordSale($order, $line);
                    $items++;
                }
            }
            $service->finishSyncLog($log, ['items_processed' => $items]);
        } catch (Throwable $e) {
            $service->failSyncLog($log, $e->getMessage());
            throw $e;
        }
    }
}
