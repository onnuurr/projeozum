<?php

namespace Modules\Tenant\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Tenant\Models\Tenant;

/**
 * Pazaryeri satışları + giderleri + faturalar üzerinden tenant'ın P&L tablosu.
 * Pazaryeri verisi MarketplaceFinancialsContract üzerinden okunur — bu servis
 * Marketplace'in tablolarını/modellerini doğrudan bilmez.
 */
class TenantFinancialsService
{
    public function __construct(private MarketplaceFinancialsContract $marketplace) {}

    public function summary(Tenant $tenant, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $sales    = $this->marketplace->salesTotal($tenant->id, $from, $to);
        $expenses = $this->marketplace->expensesTotal($tenant->id, $from, $to);

        $invoiced = (float) DB::table('tenant_invoices')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $net = $sales - $expenses - $invoiced;

        return [
            'sales_total'    => round($sales, 2),
            'expenses_total' => round($expenses, 2),
            'invoiced_total' => round($invoiced, 2),
            'net'            => round($net, 2),
            'from'           => $from->format('Y-m-d'),
            'to'             => $to->format('Y-m-d'),
        ];
    }

    public function byMarketplace(Tenant $tenant, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->marketplace->salesByMarketplace($tenant->id, $from, $to);
    }

    public function byMonth(Tenant $tenant, int $months = 12): array
    {
        return $this->marketplace->salesByMonth($tenant->id, $months);
    }
}
