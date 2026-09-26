<?php

namespace Modules\Finance\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\OrderReturn;
use Modules\Tenant\Models\Tenant;

/**
 * Bayi satış raporu: bayilerin Bagisto bayi portalı üzerinden verdiği siparişler. Product\Order
 * (+ OrderItem, OrderReturn, Tenant) tablolarını okur, hiçbir veri kopyalamaz.
 *
 * Veri kaynağı DEĞİŞTİ (2026-09-22): önceden Tenant\TenantInvoice okunuyordu (Bayi Portalı'nın
 * checkout akışından kalma bir tablo); sipariş oluşturma artık sadece Bagisto webhook'undan geliyor
 * (Modules\Bagisto\Services\BagistoOrderSyncService). `bagisto_order_id IS NOT NULL` filtresi
 * bilinçli: olası eski/manuel test verisini dışarıda bırakan ucuz bir güvenlik payı.
 *
 * Filtreler (2026-09-26): date_from, date_to, tenant_id, status, product (ürün adı/marka arama),
 * channel. Kanal SQL'de değil PHP'de süzülür: shipping_info.dropship yoksa JSON null olarak
 * saklanıyor ve Postgres'te `->` ile SQL NULL'dan ayrışıyor (sqlite testlerinde farklı davranıyor);
 * B2B hacminde filtrelenmiş siparişleri belleğe almak sorun değil.
 *
 * Ciro (brüt) iptal edilen siparişleri içermez; net = brüt − ödenmiş iadeler (OrderReturn refunded).
 */
class TenantPurchaseReportService
{
    /** Bayi portalı dropship kanalları (Bagisto Webkul\BayiPortal\Models\DropshipDetail::CHANNELS ile aynı). */
    public const CHANNELS = [
        'trendyol'    => 'Trendyol',
        'hepsiburada' => 'Hepsiburada',
        'n11'         => 'N11',
        'ciceksepeti' => 'Çiçeksepeti',
        'instagram'   => 'Instagram',
        'web'         => 'Bayinin web sitesi',
        'diger'       => 'Diğer',
    ];

    public const CHANNEL_SELF = 'self';
    public const CHANNEL_DROPSHIP_UNSPECIFIED = 'dropship';

    public static function channelLabels(): array
    {
        return [
            self::CHANNEL_SELF                 => 'Bayinin kendisi (stok)',
            ...self::CHANNELS,
            self::CHANNEL_DROPSHIP_UNSPECIFIED => 'Dropship (kanal belirtilmemiş)',
        ];
    }

    /** Siparişin satış kanalı: dropship bloğu yoksa bayi kendi stoğu için almıştır. */
    public static function channelOf(Order $order): string
    {
        $dropship = $order->shipping_info['dropship'] ?? null;

        if (empty($dropship)) {
            return self::CHANNEL_SELF;
        }

        $channel = $dropship['channel'] ?? null;

        return $channel && array_key_exists($channel, self::CHANNELS) ? $channel : ($channel ?: self::CHANNEL_DROPSHIP_UNSPECIFIED);
    }

    /**
     * Dashboard'daki KPI de bunu (filtresiz) kullanır: orderCount/totalAmount/statusCounts tüm
     * durumları kapsar; gross/net ciro iptalleri dışarıda bırakır.
     *
     * @return array{orderCount:int, totalAmount:float, statusCounts:array<string,int>, grossAmount:float, refundedAmount:float, netAmount:float, averageOrder:float, tenantCount:int, dropshipShare:float}
     */
    public function summary(array $filters = []): array
    {
        $orders = $this->orders($filters);
        $active = $orders->where('status', '!=', Order::STATUS_CANCELLED);

        $gross = round((float) $active->sum('total'), 2);
        $refunded = $this->refundedAmount($orders->pluck('id'));
        $dropshipTotal = (float) $active->filter(fn (Order $o) => self::channelOf($o) !== self::CHANNEL_SELF)->sum('total');

        return [
            'orderCount'     => $orders->count(),
            'totalAmount'    => round((float) $orders->sum('total'), 2),
            'statusCounts'   => $orders->countBy('status')->all(),
            'grossAmount'    => $gross,
            'refundedAmount' => $refunded,
            'netAmount'      => round($gross - $refunded, 2),
            'averageOrder'   => $active->count() ? round($gross / $active->count(), 2) : 0.0,
            'tenantCount'    => $active->pluck('tenant_id')->filter()->unique()->count(),
            'dropshipShare'  => $gross > 0 ? round($dropshipTotal / $gross * 100, 1) : 0.0,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(array $filters = []): Collection
    {
        $orders = $this->orders($filters);
        $labels = self::channelLabels();

        return $orders->map(fn (Order $order) => [
            'id'         => $order->id,
            'tenant'     => $order->tenant?->name ?? 'Bilinmiyor',
            'tenantCode' => $order->tenant?->code,
            'orderNo'    => $order->order_no,
            'amount'     => (float) $order->total,
            'status'     => $order->status,
            'channel'    => $labels[self::channelOf($order)] ?? self::channelOf($order),
            'payment'    => $order->payment_method,
            'itemCount'  => $order->items_count,
            'createdAt'  => optional($order->created_at)->format('Y-m-d H:i'),
        ])->values();
    }

    /**
     * Bayi bazlı özet: ciro, sipariş, iade, iade oranı, cari durum.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function byTenant(array $filters = []): Collection
    {
        $orders = $this->orders($filters)->where('status', '!=', Order::STATUS_CANCELLED);
        $refunds = $this->refundsByOrder($orders->pluck('id'));

        $tenants = Tenant::query()
            ->whereIn('id', $orders->pluck('tenant_id')->filter()->unique())
            ->get(['id', 'name', 'code', 'credit_limit', 'current_balance'])
            ->keyBy('id');

        return $orders->groupBy('tenant_id')->map(function (Collection $group, $tenantId) use ($refunds, $tenants) {
            $gross = round((float) $group->sum('total'), 2);
            $refunded = round((float) $group->sum(fn (Order $o) => $refunds[$o->id] ?? 0), 2);
            $tenant = $tenants[$tenantId] ?? null;

            return [
                'tenantId'       => $tenant?->id,
                'tenant'         => $tenant?->name ?? 'Bilinmiyor',
                'tenantCode'     => $tenant?->code,
                'orderCount'     => $group->count(),
                'grossAmount'    => $gross,
                'refundedAmount' => $refunded,
                'netAmount'      => round($gross - $refunded, 2),
                'returnRate'     => $gross > 0 ? round($refunded / $gross * 100, 1) : 0.0,
                'averageOrder'   => round($gross / max(1, $group->count()), 2),
                'lastOrderAt'    => optional($group->max('created_at'))->format('Y-m-d'),
                'creditLimit'    => $tenant ? (float) $tenant->credit_limit : null,
                'balance'        => $tenant ? (float) $tenant->current_balance : null,
            ];
        })->sortByDesc('grossAmount')->values();
    }

    /**
     * Satış kanalı kırılımı (bayinin kendisi / dropship kanalları).
     *
     * @return Collection<int, array{channel:string, label:string, orderCount:int, grossAmount:float, share:float}>
     */
    public function byChannel(array $filters = []): Collection
    {
        $orders = $this->orders($filters)->where('status', '!=', Order::STATUS_CANCELLED);
        $gross = (float) $orders->sum('total');
        $labels = self::channelLabels();

        return $orders->groupBy(fn (Order $o) => self::channelOf($o))
            ->map(fn (Collection $group, string $channel) => [
                'channel'     => $channel,
                'label'       => $labels[$channel] ?? $channel,
                'orderCount'  => $group->count(),
                'grossAmount' => round((float) $group->sum('total'), 2),
                'share'       => $gross > 0 ? round((float) $group->sum('total') / $gross * 100, 1) : 0.0,
            ])
            ->sortByDesc('grossAmount')
            ->values();
    }

    /**
     * Ürün bazlı kırılım — "hangi üründen ne kadarlık sipariş geldi". Ürün filtresi verilmişse
     * yalnız eşleşen satırlar sayılır (siparişin diğer ürünleri değil).
     *
     * @return Collection<int, array{productName:string, brand:?string, qty:int, totalAmount:float, orderCount:int}>
     */
    public function byProduct(array $filters = []): Collection
    {
        $orderIds = $this->orders($filters)->where('status', '!=', Order::STATUS_CANCELLED)->pluck('id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        return OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->when(filled($filters['product'] ?? null), fn (Builder $q) => $this->matchProduct($q, $filters['product']))
            // Ürün adına göre gruplanır (eski davranış); marka yalnız bilgi amaçlı.
            ->selectRaw('product_name, max(product_brand) as product_brand, sum(qty) as qty, sum(total_price) as total_amount, count(distinct order_id) as order_count')
            ->groupBy('product_name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row) => [
                'productName' => $row->product_name,
                'brand'       => $row->product_brand,
                'qty'         => (int) $row->qty,
                'totalAmount' => (float) $row->total_amount,
                'orderCount'  => (int) $row->order_count,
            ]);
    }

    /** Filtre seçeneği: Bagisto siparişi olan bayiler. */
    public function tenantOptions(): Collection
    {
        return Tenant::query()
            ->whereIn('id', Order::query()->whereNotNull('bagisto_order_id')->whereNotNull('tenant_id')->select('tenant_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * Filtrelenmiş Bagisto siparişleri (kanal süzmesi PHP'de, bkz. sınıf notu). En yeni önce.
     *
     * @return Collection<int, Order>
     */
    public function orders(array $filters = []): Collection
    {
        $orders = Order::query()
            ->whereNotNull('bagisto_order_id')
            ->with('tenant:id,name,code')
            ->withCount('items')
            ->when($filters['date_from'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($filters['tenant_id'] ?? null, fn (Builder $q, $id) => $q->where('tenant_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $q, $s) => $q->where('status', $s))
            ->when(filled($filters['product'] ?? null), fn (Builder $q) => $q->whereHas('items', fn (Builder $i) => $this->matchProduct($i, $filters['product'])))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'order_no', 'tenant_id', 'total', 'status', 'payment_method', 'shipping_info', 'created_at']);

        if (filled($filters['channel'] ?? null)) {
            $orders = $orders->filter(fn (Order $o) => self::channelOf($o) === $filters['channel'])->values();
        }

        return $orders;
    }

    private function matchProduct(Builder $query, string $term): Builder
    {
        $like = '%'.mb_strtolower(trim($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('lower(product_name) like ?', [$like])
            ->orWhereRaw('lower(product_brand) like ?', [$like]));
    }

    private function refundedAmount(Collection $orderIds): float
    {
        return round((float) array_sum($this->refundsByOrder($orderIds)), 2);
    }

    /** @return array<int, float> order_id => ödenmiş iade toplamı */
    private function refundsByOrder(Collection $orderIds): array
    {
        if ($orderIds->isEmpty()) {
            return [];
        }

        return OrderReturn::query()
            ->whereIn('order_id', $orderIds)
            ->where('status', OrderReturn::STATUS_REFUNDED)
            ->selectRaw('order_id, sum(approved_amount) as refunded')
            ->groupBy('order_id')
            ->pluck('refunded', 'order_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
