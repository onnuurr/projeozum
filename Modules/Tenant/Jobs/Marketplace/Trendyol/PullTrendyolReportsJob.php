<?php

namespace Modules\Tenant\Jobs\Marketplace\Trendyol;

use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\MarketplaceServiceResolver;
use Throwable;

class PullTrendyolReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(public int $tenantId, public string $fromIso, public string $toIso) {}

    public function middleware(): array
    {
        return [(new RateLimited('marketplace-trendyol'))];
    }

    public function handle(MarketplaceServiceResolver $resolver): void
    {
        $tenant  = Tenant::findOrFail($this->tenantId);
        $service = $resolver->for($tenant, 'trendyol');
        $log     = $service->startSyncLog('pull_reports');

        try {
            $service->fetchReports(new DateTimeImmutable($this->fromIso), new DateTimeImmutable($this->toIso));
            $service->finishSyncLog($log);
        } catch (Throwable $e) {
            $service->failSyncLog($log, $e->getMessage());
            throw $e;
        }
    }
}
