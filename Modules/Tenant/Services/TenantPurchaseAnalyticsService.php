<?php

namespace Modules\Tenant\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;

/**
 * Tenant'ın bizden yaptığı alımların özet/agregasyonu. Sadece okuma.
 *
 * Tüm sorgular `orders.tenant_id = $tenant->id` + `order_type = 'dropship'`
 * filtresi ile çalışır (B2C user'lar tenant'a atfedilmesin).
 */
class TenantPurchaseAnalyticsService
{
    /**
     * Top satılan ürünler — order_items üzerinden, qty toplamına göre.
     *
     * @return array<int, array{product_id:int|null,product_name:string,total_qty:int,total_revenue:float}>
     */
    public function topProducts(Tenant $tenant, int $months = 6, int $limit = 10): array
    {
        $since = Carbon::now()->subMonths($months);

        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.tenant_id', $tenant->id)
            ->where('o.created_at', '>=', $since)
            ->select(
                'oi.product_id',
                DB::raw('MAX(oi.product_name) as product_name'),
                DB::raw('SUM(oi.qty) as total_qty'),
                DB::raw('SUM(oi.total_price) as total_revenue'),
            )
            ->groupBy('oi.product_id')
            ->orderByDesc('total_qty')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'product_id'    => $row->product_id !== null ? (int) $row->product_id : null,
                'product_name'  => (string) $row->product_name,
                'total_qty'     => (int) $row->total_qty,
                'total_revenue' => (float) $row->total_revenue,
            ])
            ->all();
    }

    /**
     * Aylık alım trendi — son N ay (default 12). Driver-aware: PG → date_trunc, SQLite → strftime.
     *
     * @return array<int, array{month:string,total:float,order_count:int}>
     */
    public function monthlyTrend(Tenant $tenant, int $months = 12): array
    {
        $since   = Carbon::now()->subMonths($months)->startOfMonth();
        $driver  = DB::connection()->getDriverName();

        // YYYY-MM stringi (her driver için aynı format).
        $monthExpr = match ($driver) {
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT(created_at, '%Y-%m')",
            default  => "strftime('%Y-%m', created_at)", // sqlite
        };

        return DB::table('orders')
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', $since)
            ->select(
                DB::raw("$monthExpr as month"),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as order_count'),
            )
            ->groupBy(DB::raw($monthExpr))
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month'       => (string) $row->month,
                'total'       => (float) $row->total,
                'order_count' => (int) $row->order_count,
            ])
            ->all();
    }

    /**
     * Kredi anlık görüntüsü + son N ledger satırı.
     *
     * @return array{credit_limit:float,current_balance:float,available_credit:float,recent_ledger:array<int,array<string,mixed>>}
     */
    public function creditSnapshot(Tenant $tenant, int $recentCount = 5): array
    {
        $fresh = $tenant->fresh();

        $recent = $fresh->creditLedger()
            ->orderByDesc('created_at')
            ->limit($recentCount)
            ->get(['id', 'type', 'amount', 'reason', 'balance_after', 'created_at'])
            ->map(fn ($l) => [
                'id'            => $l->id,
                'type'          => $l->type,
                'amount'        => (float) $l->amount,
                'reason'        => $l->reason,
                'balance_after' => (float) $l->balance_after,
                'created_at'    => optional($l->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'credit_limit'     => (float) $fresh->credit_limit,
            'current_balance'  => (float) $fresh->current_balance,
            'available_credit' => (float) $fresh->credit_limit - (float) $fresh->current_balance,
            'recent_ledger'    => $recent,
        ];
    }
}
