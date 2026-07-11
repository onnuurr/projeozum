<?php

namespace Modules\Marketplace\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Modules\Marketplace\Jobs\Trendyol\PullTrendyolOrdersJob;
use Modules\Marketplace\Jobs\Trendyol\PullTrendyolReportsJob;
use Modules\Marketplace\Jobs\Trendyol\SyncTrendyolProductsJob;
use Modules\Marketplace\Models\TenantMarketplaceCredential;

/**
 * Provider-agnostic ince scheduler dispatcher. Scheduler bunu çağırır;
 * her aktif credential için doğru provider job'unu match expression ile dispatch eder.
 *
 * Provider service implementations'ı bilmez — sadece "şu tenant + şu marketplace
 * → şu job class" mapping'i.
 */
class MarketplaceSyncOrchestrator
{
    public function scheduleAllPullOrders(?DateTimeInterface $since = null): void
    {
        $sinceIso = $since?->format(DATE_ATOM);

        TenantMarketplaceCredential::query()
            ->where('is_active', true)
            ->cursor()
            ->each(function (TenantMarketplaceCredential $cred) use ($sinceIso) {
                match ($cred->marketplace) {
                    'trendyol'    => PullTrendyolOrdersJob::dispatch($cred->tenant_id, $sinceIso),
                    // Diğer 3 marketplace henüz live job'a sahip değil — sessiz geç.
                    'hepsiburada', 'n11', 'ciceksepeti' => Log::info(
                        "scheduleAllPullOrders skip: {$cred->marketplace} live job henüz yok (tenant {$cred->tenant_id})",
                    ),
                    default => null,
                };
            });
    }

    public function scheduleAllPullReports(DateTimeInterface $from, DateTimeInterface $to): void
    {
        $fromIso = $from->format(DATE_ATOM);
        $toIso   = $to->format(DATE_ATOM);

        TenantMarketplaceCredential::query()
            ->where('is_active', true)
            ->cursor()
            ->each(function (TenantMarketplaceCredential $cred) use ($fromIso, $toIso) {
                match ($cred->marketplace) {
                    'trendyol' => PullTrendyolReportsJob::dispatch($cred->tenant_id, $fromIso, $toIso),
                    default    => null,
                };
            });
    }

    /**
     * Tenant'ın bir marketplace'e ürünlerini push et — UI butonundan çağrılır.
     */
    public function pushProducts(int $tenantId, string $marketplace, array $productIds): void
    {
        match ($marketplace) {
            'trendyol' => SyncTrendyolProductsJob::dispatch($tenantId, $productIds),
            default    => throw new \InvalidArgumentException("{$marketplace} için push job yok (live henüz aktif değil)."),
        };
    }
}
