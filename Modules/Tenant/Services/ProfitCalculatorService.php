<?php

namespace Modules\Tenant\Services;

use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\DTOs\ProfitBreakdown;

/**
 * "Bu ürünü Y pazaryerinde Z TL'ye satarsam ne kazanırım?" hesabı.
 *
 * Pure service — persistence yok. Lookup:
 *  - our_cost: products.purchase_price (ana firma maliyet)
 *  - tenant_cost: TenantAccessService::priceFor (bayinin bizden aldığı fiyat)
 *  - commission/shipping: MarketplaceFinancialsContract (kategori-spesifik → default)
 *  - vat: tenant.settings['vat_rate'] (default 18)
 */
class ProfitCalculatorService
{
    public function __construct(
        private TenantAccessService $access,
        private MarketplaceFinancialsContract $marketplace,
    ) {}

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

        [$commissionRate, $shippingRate] = $this->marketplace->commissionAndShippingRates(
            $marketplace,
            (int) ($product->category_id ?? 0),
        );

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
}
