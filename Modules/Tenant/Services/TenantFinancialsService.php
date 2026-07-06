<?php

namespace Modules\Tenant\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;

/**
 * Pazaryeri satışları + giderleri + faturalar üzerinden tenant'ın P&L tablosu.
 * Driver-aware (pgsql/mysql/sqlite) month aggregator.
 */
class TenantFinancialsService
{
    public function summary(Tenant $tenant, DateTimeInterface $from, DateTimeInterface $to): array
    {
        $sales = (float) DB::table('marketplace_sales')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('sold_at', [$from, $to])
            ->sum(DB::raw('sold_price * qty'));

        $expenses = (float) DB::table('marketplace_expenses')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount');

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
        return DB::table('marketplace_sales')
            ->where('tenant_id', $tenant->id)
            ->whereBetween('sold_at', [$from, $to])
            ->select(
                'marketplace',
                DB::raw('SUM(sold_price * qty) as revenue'),
                DB::raw('SUM(commission) as commission'),
                DB::raw('SUM(net_revenue) as net'),
            )
            ->groupBy('marketplace')
            ->get()
            ->map(fn ($r) => [
                'marketplace' => $r->marketplace,
                'revenue'     => (float) $r->revenue,
                'commission'  => (float) $r->commission,
                'net'         => (float) $r->net,
            ])
            ->all();
    }

    public function byMonth(Tenant $tenant, int $months = 12): array
    {
        $since = now()->subMonths($months)->startOfMonth();
        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'pgsql'             => "to_char(sold_at, 'YYYY-MM')",
            'mysql', 'mariadb'  => "DATE_FORMAT(sold_at, '%Y-%m')",
            default             => "strftime('%Y-%m', sold_at)",
        };

        return DB::table('marketplace_sales')
            ->where('tenant_id', $tenant->id)
            ->where('sold_at', '>=', $since)
            ->select(
                DB::raw("$monthExpr as month"),
                DB::raw('SUM(sold_price * qty) as revenue'),
                DB::raw('SUM(net_revenue) as net'),
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month'   => (string) $r->month,
                'revenue' => (float) $r->revenue,
                'net'     => (float) $r->net,
            ])
            ->all();
    }
}
