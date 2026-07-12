<?php

namespace Modules\Marketplace\Services\Contracts;

use DateTimeInterface;

/**
 * Tenant modülünün pazaryeri satış/gider/komisyon verisine erişimi bu dar
 * arayüz üzerinden olur — Marketplace'in somut tablolarına/modellerine
 * (marketplace_sales, marketplace_expenses, MarketplaceCommissionRate)
 * doğrudan bağımlılık yasak. Implementasyon: MarketplaceFinancialsService.
 */
interface MarketplaceFinancialsContract
{
    public function salesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float;

    public function expensesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float;

    /**
     * @return array<int, array{marketplace: string, revenue: float, commission: float, net: float}>
     */
    public function salesByMarketplace(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): array;

    /**
     * @return array<int, array{month: string, revenue: float, net: float}>
     */
    public function salesByMonth(int $tenantId, int $months): array;

    /**
     * @return array{0:float,1:float} [commission_rate, shipping_rate]
     */
    public function commissionAndShippingRates(string $marketplace, int $categoryId): array;
}
