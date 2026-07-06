<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantCreditService;

/**
 * Checkout transaction body — B2C ve portal (dropship) controller'lar bu service'i çağırır.
 *
 * Controller sorumluluğu: validate + redirect/flash. Bu service:
 *  - tenant siparişinde sert kredi-blok (assertCanCharge + lockForUpdate charge)
 *  - Order + OrderItem yazma
 *  - cart temizleme
 *  - sıkı transaction
 */
class CheckoutService
{
    public const FREE_SHIPPING_TARGET = 500.00;
    public const SHIPPING_FEE         = 49.90;

    public function __construct(private TenantCreditService $credit) {}

    /**
     * Sepet özetinden total/shipping/discount hesabı. B2C ve portal aynı kuralı kullanır.
     */
    public function computeTotals(iterable $items, string $shippingMethod, ?string $promoCode): array
    {
        $subtotal = 0.0;
        foreach ($items as $i) {
            $subtotal += (float) $i->price * (int) $i->qty;
        }

        $shippingFee = match ($shippingMethod) {
            'express'  => 79.90,
            'same_day' => 129.90,
            default    => self::SHIPPING_FEE,
        };
        if ($subtotal >= self::FREE_SHIPPING_TARGET && $shippingMethod === 'standard') {
            $shippingFee = 0.0;
        }

        $discount = 0.0;
        $applied  = null;
        $promo    = $promoCode ? strtoupper(trim($promoCode)) : null;
        if ($promo === 'TEKSTIL10') {
            $discount = round($subtotal * 0.10, 2);
            $applied  = $promo;
        } elseif ($promo === 'WELCOME50') {
            $discount = 50.00;
            $applied  = $promo;
        }

        return [
            'subtotal'     => $subtotal,
            'shipping_fee' => $shippingFee,
            'total'        => max(0.0, $subtotal + $shippingFee - $discount),
            'discount'     => $discount,
            'promo_code'   => $applied,
        ];
    }

    /**
     * @param array $data Validated checkout payload (address, shipping_method, payment_method, card, billing, note...).
     * @param int $userId Auth user id.
     * @param ?int $tenantId B2C için null; portal/dropship için tenant_id.
     * @param iterable $items Sepete yüklü CartItem koleksiyonu (product + brand eager-loaded olabilir).
     * @param array $totals ['subtotal','shipping_fee','total','promo_code', ...].
     *
     * @throws InsufficientCreditException tenant kredisi yetmiyorsa.
     */
    public function place(array $data, int $userId, ?int $tenantId, iterable $items, array $totals): Order
    {
        // Transaction öncesi pre-check; race condition için charge() içinde lockForUpdate ile tekrar doğrulanır.
        $tenant = $tenantId !== null ? Tenant::findOrFail($tenantId) : null;
        if ($tenant !== null) {
            $this->credit->assertCanCharge($tenant, (float) $totals['total']);
        }

        return DB::transaction(function () use ($userId, $tenantId, $tenant, $items, $totals, $data) {
            $order = Order::create([
                'order_no'       => $this->generateOrderNo(),
                'user_id'        => $userId,
                'tenant_id'      => $tenantId,
                'order_type'     => $tenantId !== null ? Order::TYPE_DROPSHIP : Order::TYPE_B2C,
                'shipping_info'  => [
                    'address'           => $data['address'] ?? null,
                    'shipping_method'   => $data['shipping_method'] ?? null,
                    'card'              => $data['card']    ?? null,
                    'billing'           => $data['billing'] ?? null,
                    'installment_count' => $data['installment_count'] ?? 1,
                    'promo_code'        => $totals['promo_code'] ?? null,
                    'billing_to'        => $data['billing_to'] ?? null,
                ],
                'payment_method' => $data['payment_method'] ?? null,
                'note'           => $data['note'] ?? null,
                'subtotal'       => $totals['subtotal'],
                'shipping_fee'   => $totals['shipping_fee'],
                'total'          => $totals['total'],
                'status'         => 'pending',
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item->product_id,
                    'product_name'  => $item->product?->name ?? 'Ürün',
                    'product_brand' => $item->product?->brand?->name,
                    'product_image' => "https://picsum.photos/seed/tek-p{$item->product_id}/200/250",
                    'color'         => $item->color,
                    'size'          => $item->size,
                    'qty'           => $item->qty,
                    'unit_price'    => $item->price,
                    'total_price'   => $item->price * $item->qty,
                ]);
            }

            if ($tenant !== null) {
                $this->credit->charge(
                    tenant: $tenant,
                    amount: (float) $totals['total'],
                    reason: 'order',
                    orderId: $order->id,
                );
            }

            CartItem::query()->where('user_id', $userId)->delete();

            return $order;
        });
    }

    public function generateOrderNo(): string
    {
        return 'SIP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
