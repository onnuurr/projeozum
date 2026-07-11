<?php

namespace Modules\Tenant\Services;

use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Marketplace\Models\MarketplaceCommissionRate;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\DTOs\ProfitBreakdown;

/**
 * "Bu ürünü Y pazaryerinde Z TL'ye satarsam ne kazanırım?" hesabı.
 *
 * Pure service — persistence yok. Lookup:
 *  - our_cost: products.purchase_price (ana firma maliyet)
 *  - tenant_cost: TenantAccessService::priceFor (bayinin bizden aldığı fiyat)
 *  - commission/shipping: marketplace_commission_rates (kategori-spesifik → default)
 *  - vat: tenant.settings['vat_rate'] (default 18)
 */
class ProfitCalculatorService
{
    public function __construct(private TenantAccessService $access) {}

    public function calculate(
        Tenant $tenant,
        Product $product,
        ?ProductVariant $variant,
        string $marketplace,
        float $sellPrice,
        int $qty = 1,
    ): ProfitBreakdown {
        $ourCost    = (float) ($product->purchase_price ?? 0);
        $tenantCost = $this->access->priceFor($tenant, $product, $variant);

        // Komisyon/kargo lookup — kategori-spesifik beats default.
        [$commissionRate, $shippingRate] = $this->lookupRates($marketplace, (int) ($product->category_id ?? 0));

        $gross      = $sellPrice * $qty;
        $commission = $gross * ($commissionRate / 100);
        $shipping   = $gross * ($shippingRate / 100);

        // VAT — tenant ayarlarından flat oran (default 18%).
        $settings = $tenant->settings ?? [];
        $vatRate  = (float) ($settings['vat_rate'] ?? 18);
        $vat      = $gross * ($vatRate / 100) / (1 + $vatRate / 100); // KDV dahil fiyattan KDV çıkar

        $costTotal = ($tenantCost * $qty) + $commission + $shipping + $vat;
        $netProfit = $gross - $costTotal;
        $marginPct = $gross > 0 ? ($netProfit / $gross) * 100 : 0;

        return new ProfitBreakdown(
            ourCost: $ourCost * $qty,
            tenantCost: $tenantCost * $qty,
            sellPrice: $gross,
            qty: $qty,
            commission: $commission,
            shipping: $shipping,
            vat: $vat,
            netProfit: $netProfit,
            marginPct: $marginPct,
            marketplace: $marketplace,
        );
    }

    /**
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    private function lookupRates(string $marketplace, int $categoryId): array
    {
        $today = now()->toDateString();

        $row = MarketplaceCommissionRate::query()
            ->where('marketplace', $marketplace)
            ->where('category_id', $categoryId)
            ->where('valid_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
            ->orderByDesc('valid_from')
            ->first();

        if (! $row) {
            $row = MarketplaceCommissionRate::query()
                ->where('marketplace', $marketplace)
                ->whereNull('category_id')
                ->where('valid_from', '<=', $today)
                ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', $today))
                ->orderByDesc('valid_from')
                ->first();
        }

        return $row ? [(float) $row->commission_rate, (float) $row->shipping_rate] : [0.0, 0.0];
    }
}
