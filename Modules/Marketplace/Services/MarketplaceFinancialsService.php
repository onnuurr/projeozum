<?php

namespace Modules\Marketplace\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;

class MarketplaceFinancialsService implements MarketplaceFinancialsContract
{
    public function __construct(private CommissionRateLookup $rates) {}

    public function salesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float
    {
        return (float) DB::table('marketplace_sales')
            ->where('tenant_id', $tenantId)
            ->whereBetween('sold_at', [$from, $to])
            ->sum(DB::raw('sold_price * qty'));
    }

    public function expensesTotal(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): float
    {
        return (float) DB::table('marketplace_expenses')
            ->where('tenant_id', $tenantId)
            ->whereBetween('occurred_at', [$from, $to])
            ->sum('amount');
    }

    public function salesByMarketplace(int $tenantId, DateTimeInterface $from, DateTimeInterface $to): array
    {
        return DB::table('marketplace_sales')
            ->where('tenant_id', $tenantId)
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

    public function salesByMonth(int $tenantId, int $months): array
    {
        $since = now()->subMonths($months)->startOfMonth();
        $driver = DB::connection()->getDriverName();
        $monthExpr = match ($driver) {
            'pgsql'             => "to_char(sold_at, 'YYYY-MM')",
            'mysql', 'mariadb'  => "DATE_FORMAT(sold_at, '%Y-%m')",
            default             => "strftime('%Y-%m', sold_at)",
        };

        return DB::table('marketplace_sales')
            ->where('tenant_id', $tenantId)
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

    public function commissionAndShippingRates(string $marketplace, int $categoryId): array
    {
        return $this->rates->rates($marketplace, $categoryId);
    }
}
