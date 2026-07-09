<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\UserAddress;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Exceptions\InsufficientCreditException;

class CheckoutController extends Controller
{
    private const FREE_SHIPPING_TARGET = 500.00;
    private const SHIPPING_FEE         = 49.90;

    private const PAYMENT_METHODS = ['card', 'bank', 'cod'];

    public function index(Request $request): Response|RedirectResponse
    {
        $items = $this->loadCartItems($request->user()->id);

        if ($items->isEmpty()) {
            return redirect()->route('products.index')->with('flash', [
                'toast' => [
                    'type'    => 'info',
                    'title'   => 'Sepetiniz boş',
                    'message' => 'Ödeme adımına geçmek için önce ürün ekleyin.',
                ],
            ]);
        }

        $user = $request->user();

        return Inertia::render('Product::Checkout', [
            'addresses'        => $this->addressesFor($user),
            'shippingMethods'  => $this->shippingMethods(),
            'installmentPlans' => $this->installmentPlans(),
            'savedCards'       => [],
            'userEmail'        => $user->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'address'                  => ['required', 'array'],
            'address.label'            => ['nullable', 'string', 'max:32'],
            'address.name'             => ['required', 'string', 'max:120'],
            'address.phone'            => ['required', 'string', 'max:32'],
            'address.street'           => ['required', 'string', 'max:500'],
            'address.district'         => ['nullable', 'string', 'max:80'],
            'address.city'             => ['required', 'string', 'max:80'],
            'address.postal_code'      => ['nullable', 'string', 'max:16'],
            'shipping_method'          => ['required', 'string', 'max:32'],
            'payment_method'           => ['required', Rule::in(self::PAYMENT_METHODS)],
            'card'                     => ['nullable', 'array'],
            'card.last4'               => ['nullable', 'string', 'max:4'],
            'card.brand'               => ['nullable', 'string', 'max:24'],
            'card.holder'              => ['nullable', 'string', 'max:120'],
            'installment_count'        => ['nullable', 'integer', 'min:1', 'max:24'],
            'billing'                  => ['nullable', 'array'],
            'billing.type'             => ['nullable', Rule::in(['individual', 'company'])],
            'billing.same_as_shipping' => ['nullable', 'boolean'],
            'billing.company_name'     => ['nullable', 'string', 'max:160'],
            'billing.tax_office'       => ['nullable', 'string', 'max:80'],
            'billing.tax_number'       => ['nullable', 'string', 'max:32'],
            'promo_code'               => ['nullable', 'string', 'max:32'],
            'note'                     => ['nullable', 'string', 'max:500'],
            'terms_accepted'           => ['accepted'],
        ]);

        $userId = $request->user()->id;
        $items  = $this->loadCartItems($userId);

        if ($items->isEmpty()) {
            return redirect()->route('products.index')->with('flash', [
                'toast' => [
                    'type'    => 'warning',
                    'title'   => 'Sepetiniz boş',
                    'message' => 'Sipariş oluşturmak için sepete ürün ekleyin.',
                ],
            ]);
        }

        // Tenant kullanıcı: sepetteki ürünler hâlâ erişilebilir mi? (sepete eklendikten sonra
        // marka/kategori kuralı veya override ile bloklanmış olabilir — CartController@add ile aynı kontrol.)
        $user     = $request->user();
        $tenantId = (! $user->isSuperadmin() && $user->tenant_id) ? $user->tenant_id : null;
        if ($tenantId !== null) {
            $productIds    = $items->pluck('product_id')->unique()->all();
            $accessibleIds = Product::query()
                ->accessibleToTenant($tenantId)
                ->whereKey($productIds)
                ->pluck('id')
                ->all();

            if (array_diff($productIds, $accessibleIds) !== []) {
                return redirect()->route('checkout.index')->with('flash', [
                    'toast' => [
                        'type'    => 'warning',
                        'title'   => 'Sepette erişilemeyen ürün var',
                        'message' => 'Artık görüntüleme yetkiniz olmayan ürünleri sepetten çıkarın.',
                    ],
                ]);
            }
        }

        // Tenant-only B2B: sipariş kesimi (yeniden fiyatlama + stok/kredi) yalnız tenant
        // bağlamında anlamlıdır. Tenant'sız (saf B2C) checkout artık desteklenmiyor.
        if ($tenantId === null) {
            return redirect()->route('checkout.index')->with('flash', [
                'toast' => [
                    'type'    => 'warning',
                    'title'   => 'Sipariş oluşturulamadı',
                    'message' => 'Bu hesap bir bayiye bağlı değil.',
                ],
            ]);
        }

        $totals = $this->totalsFor($items, $data['shipping_method'] ?? 'standard', $data['promo_code'] ?? null);

        try {
            $order = app(CheckoutService::class)->place($data, $userId, $tenantId, $items, $totals);
        } catch (InsufficientCreditException $e) {
            return redirect()->route('checkout.index')->with('flash', [
                'toast' => [
                    'type'    => 'error',
                    'title'   => 'Kredi limiti yetersiz',
                    'message' => sprintf(
                        'Toplam %.2f ₺, kullanılabilir kredi %.2f ₺.',
                        $e->requestedAmount,
                        $e->availableCredit,
                    ),
                ],
            ]);
        } catch (InsufficientStockException $e) {
            return redirect()->route('checkout.index')->with('flash', [
                'toast' => [
                    'type'    => 'error',
                    'title'   => 'Stok yetersiz',
                    'message' => sprintf('Sepetteki bir ürün için yeterli stok yok (mevcut: %d).', $e->available),
                ],
            ]);
        }

        return redirect()->route('products.index')->with('flash', [
            'toast' => [
                'type'    => 'success',
                'title'   => "Siparişiniz oluşturuldu · {$order->order_no}",
                'message' => 'Toplam ' . number_format((float) $order->total, 2, ',', '.') . ' ₺',
            ],
        ]);
    }

    private function loadCartItems(int $userId)
    {
        return CartItem::query()
            ->with([
                'product:id,name,slug,brand_id',
                'product.brand:id,name',
            ])
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();
    }

    private function addressesFor($user): array
    {
        return UserAddress::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get()
            ->map(fn (UserAddress $a) => $a->toCheckoutArray())
            ->all();
    }

    private function shippingMethods(): array
    {
        return [
            [
                'id'          => 'standard',
                'label'       => 'Standart Kargo',
                'eta'         => '3-5 iş günü',
                'description' => 'Yurtdışı hariç tüm Türkiye',
                'price'       => self::SHIPPING_FEE,
                'priceLabel'  => '₺' . number_format(self::SHIPPING_FEE, 2, ',', '.'),
            ],
            [
                'id'          => 'express',
                'label'       => 'Hızlı Kargo',
                'eta'         => '1-2 iş günü',
                'description' => 'Aynı gün hazırlanır, sonraki iş günü kargoya verilir',
                'price'       => 79.90,
                'priceLabel'  => '₺79,90',
            ],
            [
                'id'          => 'same_day',
                'label'       => 'Aynı Gün Teslimat',
                'eta'         => 'Bugün',
                'description' => 'İstanbul içi · 14:00\'a kadar verilen siparişler',
                'price'       => 129.90,
                'priceLabel'  => '₺129,90',
            ],
        ];
    }

    private function installmentPlans(): array
    {
        return [
            ['count' => 1, 'rate' => 0.0, 'label' => 'Tek Çekim'],
            ['count' => 3, 'rate' => 0.0, 'label' => '3 Taksit'],
            ['count' => 6, 'rate' => 3.5, 'label' => '6 Taksit'],
            ['count' => 9, 'rate' => 6.5, 'label' => '9 Taksit'],
            ['count' => 12, 'rate' => 9.5, 'label' => '12 Taksit'],
        ];
    }

    private function totalsFor($items, string $shippingMethod, ?string $promoCode): array
    {
        $subtotal     = (float) $items->sum(fn ($i) => $i->price * $i->qty);
        $shipping     = collect($this->shippingMethods())->firstWhere('id', $shippingMethod);
        $shippingFee  = (float) ($shipping['price'] ?? self::SHIPPING_FEE);
        if ($subtotal >= self::FREE_SHIPPING_TARGET && $shippingMethod === 'standard') {
            $shippingFee = 0.0;
        }

        $discount         = 0.0;
        $appliedPromoCode = null;
        $promo            = $promoCode ? strtoupper(trim($promoCode)) : null;
        if ($promo === 'TEKSTIL10') {
            $discount         = round($subtotal * 0.10, 2);
            $appliedPromoCode = $promo;
        } elseif ($promo === 'WELCOME50') {
            $discount         = 50.00;
            $appliedPromoCode = $promo;
        }

        return [
            'subtotal'     => $subtotal,
            'shipping_fee' => $shippingFee,
            'total'        => max(0.0, $subtotal + $shippingFee - $discount),
            'discount'     => $discount,
            'promo_code'   => $appliedPromoCode,
        ];
    }

}
