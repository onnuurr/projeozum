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

class PullTrendyolReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, RunsMarketplaceSyncLog;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(public int $tenantId, public string $fromIso, public string $toIso) {}

    public function middleware(): array
    {
        return [(new RateLimited('marketplace-trendyol'))];
    }

    public function handle(MarketplaceClientGateway $gateway, TenantMarketplaceSyncService $sync): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);
        $client = $gateway->resolveClient($tenant, 'trendyol');

        $this->runSync($sync, $tenant, 'trendyol', 'pull_reports', function () use ($client) {
            $client->fetchReports(new DateTimeImmutable($this->fromIso), new DateTimeImmutable($this->toIso));

            return [];
        });
    }
}
