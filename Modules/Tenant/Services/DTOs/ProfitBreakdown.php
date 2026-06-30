<?php

namespace Modules\Tenant\Services\DTOs;

final class ProfitBreakdown
{
    public function __construct(
        public readonly float $ourCost,        // products.purchase_price (üretim/alım maliyetimiz)
        public readonly float $tenantCost,     // bayinin bizden alış fiyatı (TenantAccessService::priceFor)
        public readonly float $sellPrice,      // bayinin müşteriden alacağı (input)
        public readonly int $qty,
        public readonly float $commission,     // pazaryeri komisyonu (lookup)
        public readonly float $shipping,       // kargo maliyeti
        public readonly float $vat,            // KDV — tenant.settings vat_rate'e göre
        public readonly float $netProfit,
        public readonly float $marginPct,
        public readonly string $marketplace,
    ) {}

    public function toArray(): array
    {
        return [
            'our_cost'    => round($this->ourCost, 2),
            'tenant_cost' => round($this->tenantCost, 2),
            'sell_price'  => round($this->sellPrice, 2),
            'qty'         => $this->qty,
            'commission'  => round($this->commission, 2),
            'shipping'    => round($this->shipping, 2),
            'vat'         => round($this->vat, 2),
            'net_profit'  => round($this->netProfit, 2),
            'margin_pct'  => round($this->marginPct, 2),
            'marketplace' => $this->marketplace,
        ];
    }
}
