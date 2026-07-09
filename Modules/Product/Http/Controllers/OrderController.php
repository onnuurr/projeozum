<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Exceptions\InvalidOrderTransitionException;
use Modules\Product\Http\Requests\UpdateOrderStatusRequest;
use Modules\Product\Models\Order;
use Modules\Product\Services\OrderService;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;

/**
 * Admin sipariş yönetimi (Faz 2). Portal tarafı (salt-okuma, subdomain) ayrıdır;
 * bu controller ana domain'de `order.view`/`order.manage` yetkileriyle korunur.
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): Response
    {
        $query = Order::query()
            ->with(['tenant:id,code,name', 'items:id,order_id'])
            ->withCount('items')
            ->latest('created_at');

        if ($status = $request->string('status')->toString()) {
            $query->status($status);
        }
        if ($tenantId = $request->integer('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        if ($orderNo = $request->string('order_no')->toString()) {
            $query->where('order_no', 'like', '%' . $orderNo . '%');
        }
        if ($from = $request->string('date_from')->toString()) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->string('date_to')->toString()) {
            $query->whereDate('created_at', '<=', $to);
        }

        $orders = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id'         => $o->id,
                'order_no'   => $o->order_no,
                'status'     => $o->status,
                'order_type' => $o->order_type,
                'tenant'     => $o->tenant ? ['id' => $o->tenant->id, 'code' => $o->tenant->code, 'name' => $o->tenant->name] : null,
                'item_count' => $o->items_count,
                'total'      => (float) $o->total,
                'created_at' => optional($o->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Product::Orders', [
            'orders'   => $orders,
            'statuses' => Order::statuses(),
            'tenants'  => Tenant::query()->orderBy('name')->get(['id', 'code', 'name']),
            'filters'  => [
                'status'    => $request->string('status')->toString(),
                'tenant_id' => $request->integer('tenant_id'),
                'order_no'  => $request->string('order_no')->toString(),
                'date_from' => $request->string('date_from')->toString(),
                'date_to'   => $request->string('date_to')->toString(),
            ],
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load([
            'tenant:id,code,name',
            'items',
            'statusHistories' => fn ($q) => $q->with('user:id,name')->orderBy('id'),
        ]);

        $ledger = TenantCreditLedger::query()
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get()
            ->map(fn (TenantCreditLedger $l) => [
                'id'            => $l->id,
                'type'          => $l->type,
                'amount'        => (float) $l->amount,
                'reason'        => $l->reason,
                'balance_after' => (float) $l->balance_after,
                'created_at'    => optional($l->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Product::OrderDetail', [
            'order' => [
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
                'tenant'         => $order->tenant ? ['id' => $order->tenant->id, 'code' => $order->tenant->code, 'name' => $order->tenant->name] : null,
                'items'          => $order->items->map(fn ($i) => [
                    'id'                => $i->id,
                    'product_id'        => $i->product_id,
                    'product_name'      => $i->product_name,
                    'product_brand'     => $i->product_brand,
                    'product_image_url' => $i->product_image_url,
                    'color'             => $i->color,
                    'size'              => $i->size,
                    'qty'               => (int) $i->qty,
                    'unit_price'        => (float) $i->unit_price,
                    'total_price'       => (float) $i->total_price,
                ])->all(),
                'histories'      => $order->statusHistories->map(fn ($h) => [
                    'id'          => $h->id,
                    'from_status' => $h->from_status,
                    'to_status'   => $h->to_status,
                    'user'        => $h->user?->name,
                    'note'        => $h->note,
                    'created_at'  => optional($h->created_at)->toIso8601String(),
                ])->all(),
            ],
            'statuses'           => Order::statuses(),
            'allowedTransitions' => Order::allowedTransitions()[$order->status] ?? [],
            'ledger'             => $ledger,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): RedirectResponse
    {
        try {
            $this->orders->transition(
                $order,
                $request->validated('status'),
                $request->user(),
                $request->validated('note'),
            );
        } catch (InvalidOrderTransitionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Sipariş durumu güncellendi.');
    }
}
