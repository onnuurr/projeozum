<?php

namespace Modules\Tenant\Jobs\Marketplace\Trendyol;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\MarketplaceServiceResolver;
use Modules\Tenant\Services\Marketplace\Trendyol\TrendyolOrderMapper;
use Throwable;

class ProcessTrendyolWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $tenantId, public array $payload) {}

    public function handle(MarketplaceServiceResolver $resolver): void
    {
        $tenant  = Tenant::findOrFail($this->tenantId);
        $service = $resolver->for($tenant, 'trendyol');
        $log     = $service->startSyncLog('webhook');

        try {
            // Trendyol webhook payload tipi farklı olabilir (order-status-change vs.).
            // İlk teslimde sade: order DTO map'le ve recordSale çağır.
            if (isset($this->payload['orderNumber']) || isset($this->payload['order'])) {
                $orderPayload = $this->payload['order'] ?? $this->payload;
                $order = (new TrendyolOrderMapper())->fromArray($orderPayload);
                $count = 0;
                foreach ($order->lines as $line) {
                    $service->recordSale($order, $line);
                    $count++;
                }
                $service->finishSyncLog($log, ['items_processed' => $count]);
            } else {
                $service->finishSyncLog($log, ['items_processed' => 0]);
            }
        } catch (Throwable $e) {
            $service->failSyncLog($log, $e->getMessage());
            throw $e;
        }
    }
}
