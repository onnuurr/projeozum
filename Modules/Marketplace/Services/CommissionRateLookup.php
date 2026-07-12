<?php

namespace Modules\Marketplace\Services;

use Modules\Marketplace\Models\MarketplaceCommissionRate;

/**
 * Komisyon ve kargo oranı lookup'ı: önce kategori-spesifik kayıt, yoksa default
 * (category_id NULL). AbstractMarketplaceService ve MarketplaceFinancialsService
 * ortak kullanır — tek kaynak, kopya sorgu yok.
 */
class CommissionRateLookup
{
    /**
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    public function rates(string $marketplace, int $categoryId): array
    {
        $today = now()->toDateString();

        $row = MarketplaceCommissionRate::query()
            ->where('marketplace', $marketplace)
            ->where('category_id', $categoryId)
            ->where('valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (! $row) {
            $row = MarketplaceCommissionRate::query()
                ->where('marketplace', $marketplace)
                ->whereNull('category_id')
                ->where('valid_from', '<=', $today)
                ->where(function ($q) use ($today) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today);
                })
                ->orderByDesc('valid_from')
                ->first();
        }

        if (! $row) {
            return [0.0, 0.0];
        }

        return [(float) $row->commission_rate, (float) $row->shipping_rate];
    }
}
