<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;

/**
 * Tenant'ların Bagisto üzerinden verdiği siparişlerin raporu. Product\Order
 * (+ OrderItem, Tenant) tablolarını okur, hiçbir veri kopyalamaz.
 *
 * Veri kaynağı DEĞİŞTİ (2026-09-22): önceden Tenant\TenantInvoice okunuyordu
 * (Bayi Portalı'nın checkout akışından kalma bir tablo). Portal kalktı ve
 * sipariş oluşturma artık sadece Bagisto webhook'undan geliyor (bkz.
 * Modules\Bagisto\Services\BagistoOrderSyncService) — bu rapor artık gerçek
 * sipariş kaynağını okuyor. `bagisto_order_id IS NOT NULL` filtresi bilinçli:
 * raporun adı/amacı "Bagisto siparişleri" — şu an tüm Order satırları zaten
 * bu yoldan geliyor ama filtre, olası eski/manuel test verisini dışarıda
 * bırakan ucuz bir güvenlik payı. TenantInvoice hâlâ var ama bu raporun
 * konusu değil (bkz. docs/superpowers/specs/2026-09-22-modul-calisma-
 * mantigi-revizyonu-design.md §4).
 */
class TenantPurchaseReportService
{
    /**
     * @return array{orderCount:int, totalAmount:float, statusCounts:array<string,int>}
     */
    public function summary(): array
    {
        $base = Order::query()->whereNotNull('bagisto_order_id');

        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        return [
            'orderCount'   => (int) (clone $base)->count(),
            'totalAmount'  => (float) (clone $base)->sum('total'),
            'statusCounts' => $statusCounts,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        return Order::query()
            ->whereNotNull('bagisto_order_id')
            ->with(['tenant:id,name,code'])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'id'         => $order->id,
                'tenant'     => $order->tenant?->name ?? 'Bilinmiyor',
                'tenantCode' => $order->tenant?->code,
                'orderNo'    => $order->order_no,
                'amount'     => (float) $order->total,
                'status'     => $order->status,
                'itemCount'  => $order->items_count,
                'createdAt'  => optional($order->created_at)->format('Y-m-d H:i'),
            ]);
    }

    /**
     * Ürün bazlı kırılım — "hangi üründen ne kadarlık sipariş geldi" (tasarım
     * kararının asıl istediği görünüm, bkz. sınıf docblock'u).
     *
     * @return Collection<int, array{productName:string, qty:int, totalAmount:float, orderCount:int}>
     */
    public function byProduct(): Collection
    {
        return OrderItem::query()
            ->whereHas('order', fn ($q) => $q->whereNotNull('bagisto_order_id'))
            ->selectRaw('product_name, sum(qty) as qty, sum(total_price) as total_amount, count(distinct order_id) as order_count')
            ->groupBy('product_name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row) => [
                'productName' => $row->product_name,
                'qty'         => (int) $row->qty,
                'totalAmount' => (float) $row->total_amount,
                'orderCount'  => (int) $row->order_count,
            ]);
    }
}
