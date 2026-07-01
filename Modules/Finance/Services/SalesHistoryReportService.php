<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Product\Models\Order;

/**
 * Bugüne kadarki tüm satışların raporu. Product\Order tablosunu okur,
 * hiçbir veri kopyalamaz.
 */
class SalesHistoryReportService
{
    /**
     * @return array{orderCount: int, totalRevenue: float, totalSubtotal: float, totalShippingFee: float}
     */
    public function summary(): array
    {
        return [
            'orderCount'       => Order::query()->count(),
            'totalRevenue'     => (float) Order::query()->sum('total'),
            'totalSubtotal'    => (float) Order::query()->sum('subtotal'),
            'totalShippingFee' => (float) Order::query()->sum('shipping_fee'),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function list(): Collection
    {
        return Order::query()
            ->with('user:id,name,email')
            ->withCount('items')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'id'          => $order->id,
                'orderNo'     => $order->order_no,
                'customer'    => $order->user?->name ?: $order->user?->email ?: 'Bilinmiyor',
                'itemCount'   => $order->items_count,
                'subtotal'    => (float) $order->subtotal,
                'shippingFee' => (float) $order->shipping_fee,
                'total'       => (float) $order->total,
                'status'      => $order->status,
                'createdAt'   => optional($order->created_at)->format('Y-m-d H:i'),
            ]);
    }
}
