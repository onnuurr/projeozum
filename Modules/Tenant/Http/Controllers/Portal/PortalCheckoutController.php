<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Exceptions\MinimumOrderException;
use Modules\Product\Models\CartItem;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Http\Requests\StoreDropshipOrderRequest;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantCreditService;

class PortalCheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private TenantCreditService $credit,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $userId = $request->user()->id;

        $items = $this->loadCart($userId);
        if ($items->isEmpty()) {
            return redirect('/catalog')->with('flash', [
                'toast' => [
                    'type'    => 'info',
                    'title'   => 'Sepet boş',
                    'message' => 'Önce katalogdan ürün ekleyin.',
                ],
            ]);
        }

        $totals    = $this->checkout->quote($tenant, $items, 'cargo');
        $available = $this->credit->availableCreditFor($tenant);

        return Inertia::render('Tenant::Portal/Checkout', [
            'tenant'   => [
                'id'                => $tenant->id,
                'code'              => $tenant->code,
                'name'              => $tenant->name,
                'slug'              => $tenant->slug,
                'address'           => $tenant->address,
                'city'              => $tenant->city,
                'phone'             => $tenant->phone,
                'discount_rate'     => (float) $tenant->discount_rate,
                'min_order_total'   => $tenant->min_order_total !== null ? (float) $tenant->min_order_total : null,
                'payment_term_days' => (int) $tenant->payment_term_days,
            ],
            'items'    => $items->map(fn ($i) => [
                'id'             => $i->id,
                'product_name'   => $i->product?->name,
                'color'          => $i->color,
                'size'           => $i->size,
                'qty'            => (int) $i->qty,
                'price'          => (float) $i->price,
                'min_order_qty'  => $i->product?->min_order_qty,
                'order_multiple' => $i->product?->order_multiple,
            ])->all(),
            'totals'   => $totals,
            'shippingMethods' => array_map(
                fn ($id, $m) => ['id' => $id, 'label' => $m['label'], 'description' => $m['description']],
                array_keys(config('product.shipping.methods', [])),
                array_values(config('product.shipping.methods', [])),
            ),
            'carriers' => \Modules\Product\Models\Carrier::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name])
                ->all(),
            'credit'   => [
                'available'   => $available,
                'after_order' => max(0.0, $available - (float) $totals['total']),
            ],
        ]);
    }

    public function store(StoreDropshipOrderRequest $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');
        $userId = $request->user()->id;

        $items = $this->loadCart($userId);
        if ($items->isEmpty()) {
            return redirect('/catalog')->with('flash', [
                'toast' => ['type' => 'warning', 'title' => 'Sepet boş', 'message' => 'Önce katalogdan ürün ekleyin.'],
            ]);
        }

        $data   = $request->validated();
        $totals = $this->checkout->quote($tenant, $items, $data['shipping_method']);

        try {
            $order = $this->checkout->place($data, $userId, $tenant->id, $items, $totals);
        } catch (MinimumOrderException $e) {
            return redirect('/checkout')->with('flash', [
                'toast' => [
                    'type'    => 'warning',
                    'title'   => 'Minimum sipariş kuralı',
                    'message' => $e->getMessage(),
                ],
            ]);
        } catch (InsufficientCreditException $e) {
            return redirect('/checkout')->with('flash', [
                'toast' => [
                    'type'    => 'error',
                    'title'   => 'Kredi limiti yetersiz',
                    'message' => sprintf('Toplam %.2f ₺, kullanılabilir kredi %.2f ₺.', $e->requestedAmount, $e->availableCredit),
                ],
            ]);
        } catch (InsufficientStockException $e) {
            return redirect('/checkout')->with('flash', [
                'toast' => [
                    'type'    => 'error',
                    'title'   => 'Stok yetersiz',
                    'message' => sprintf('Sepetteki bir ürün için yeterli stok yok (mevcut: %d).', $e->available),
                ],
            ]);
        }

        return redirect("/orders/{$order->id}")->with('flash', [
            'toast' => [
                'type'    => 'success',
                'title'   => "Siparişiniz oluşturuldu · {$order->order_no}",
                'message' => 'Toplam ' . number_format((float) $order->total, 2, ',', '.') . ' ₺',
            ],
        ]);
    }

    private function loadCart(int $userId)
    {
        return CartItem::query()
            ->with(['product:id,name,slug,brand_id,min_order_qty,order_multiple', 'product.brand:id,name'])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();
    }
}
