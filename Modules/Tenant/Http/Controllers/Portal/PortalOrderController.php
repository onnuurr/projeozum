<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;

class PortalOrderController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $orders = Order::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (Order $o) => [
                'id'          => $o->id,
                'order_no'    => $o->order_no,
                'status'      => $o->status,
                'order_type'  => $o->order_type,
                'total'       => (float) $o->total,
                'created_at'  => optional($o->created_at)->toIso8601String(),
                'item_count'  => $o->items()->count(),
            ]);

        return Inertia::render('Tenant::Portal/Orders', [
            'tenant'   => $this->tenantPayload($tenant),
            'orders'   => $orders,
            'statuses' => Order::statuses(),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load('items');

        return Inertia::render('Tenant::Portal/OrderDetail', [
            'tenant'   => $this->tenantPayload($request->attributes->get('tenant')),
            'statuses' => Order::statuses(),
            'order'  => [
                'id'             => $order->id,
                'order_no'       => $order->order_no,
                'status'         => $order->status,
                'order_type'     => $order->order_type,
                'subtotal'       => (float) $order->subtotal,
                'shipping_fee'   => (float) $order->shipping_fee,
                'total'          => (float) $order->total,
                'shipping_info'  => $order->shipping_info,
                'payment_method' => $order->payment_method,
                'note'           => $order->note,
                'created_at'     => optional($order->created_at)->toIso8601String(),
                'items'          => $order->items->map(fn ($i) => [
                    'id'           => $i->id,
                    'product_id'   => $i->product_id,
                    'product_name' => $i->product_name,
                    'product_brand'=> $i->product_brand,
                    'color'        => $i->color,
                    'size'         => $i->size,
                    'qty'          => (int) $i->qty,
                    'unit_price'   => (float) $i->unit_price,
                    'total_price'  => (float) $i->total_price,
                ])->all(),
            ],
        ]);
    }

    private function tenantPayload(Tenant $t): array
    {
        return [
            'id'   => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'slug' => $t->slug,
        ];
    }
}
