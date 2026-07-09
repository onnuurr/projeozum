<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;
use Modules\Tenant\Services\TenantCreditService;

/**
 * Checkout transaction gövdesi — portal (dropship) controller bu service'i çağırır.
 *
 * D1/D4: sepet fiyatı (cart_items.price) yalnız görüntü amaçlıdır; place() her satırı
 * transaction içinde TenantAccessService::priceFor() ile YENİDEN fiyatlar. Toplamlar
 * sunucuda yeniden-fiyatlanan satırlardan hesaplanır; controller'dan gelen $totals
 * yalnız görüntü amaçlıdır. Stok, kredi çekiminden ÖNCE StockService ile sert düşülür.
 */
class CheckoutService
{
    public const FREE_SHIPPING_TARGET = 500.00;
    public const SHIPPING_FEE         = 49.90;

    public function __construct(
        private TenantCreditService $credit,
        private TenantAccessService $access,
        private StockService $stock,
    ) {}

    /**
     * Sepet özetinden total/shipping/discount hesabı (yalnız görüntü — index sayfası).
     */
    public function computeTotals(iterable $items, string $shippingMethod, ?string $promoCode): array
    {
        $subtotal = 0.0;
        foreach ($items as $i) {
            $subtotal += (float) $i->price * (int) $i->qty;
        }

        return $this->totalsFromSubtotal($subtotal, $shippingMethod, $promoCode);
    }

    /**
     * Ara toplamdan kargo + promosyon uygulayarak toplamları üretir. computeTotals ve
     * place() bu tek kuralı paylaşır (fark: girdi ara toplamının kaynağı).
     */
    private function totalsFromSubtotal(float $subtotal, string $shippingMethod, ?string $promoCode): array
    {
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
     * Sipariş kesimi. Fiyatlar yeniden çözülür, kapak görseli snapshot'lanır, stok sert
     * düşülür ve kredi çekilir — hepsi tek transaction içinde.
     *
     * @param  array     $data      Validated checkout payload (address, shipping_method, ...).
     * @param  int       $userId    Auth user id.
     * @param  int       $tenantId  Sipariş sahibi tenant (tenant-only B2B — zorunlu).
     * @param  iterable  $items     Sepete yüklü CartItem koleksiyonu.
     * @param  array     $totals    Görüntü amaçlı toplam (para için GÜVENİLMEZ; server yeniden hesaplar).
     *
     * @throws InsufficientCreditException tenant kredisi yetmiyorsa.
     * @throws InsufficientStockException  stok yetmiyorsa.
     */
    public function place(array $data, int $userId, int $tenantId, iterable $items, array $totals): Order
    {
        $tenant = Tenant::with('type')->findOrFail($tenantId);

        return DB::transaction(function () use ($userId, $tenant, $items, $data) {
            $shippingMethod = $data['shipping_method'] ?? 'standard';

            // 1) Satırları yeniden fiyatla + kapak görselini snapshot'la.
            $lines    = [];
            $subtotal = 0.0;
            foreach ($items as $item) {
                $product = Product::with('brand')->find($item->product_id);
                $variant = $item->variant_id ? ProductVariant::find($item->variant_id) : null;

                $unitPrice = $product
                    ? $this->access->priceFor($tenant, $product, $variant)
                    : (float) $item->price;

                $lines[] = [
                    'order_id'           => null, // aşağıda doldurulur
                    'product_id'         => $item->product_id,
                    'product_variant_id' => $item->variant_id,
                    'product_name'       => $product?->name ?? 'Ürün',
                    'product_brand'      => $product?->brand?->name,
                    'product_image'      => $this->coverImagePath($item->product_id),
                    'color'              => $item->color,
                    'size'               => $item->size,
                    'qty'                => (int) $item->qty,
                    'unit_price'         => $unitPrice,
                    'total_price'        => $unitPrice * (int) $item->qty,
                ];

                $subtotal += $unitPrice * (int) $item->qty;
            }

            // 2) Toplamları sunucuda yeniden-fiyatlanan satırlardan hesapla.
            $serverTotals = $this->totalsFromSubtotal($subtotal, $shippingMethod, $data['promo_code'] ?? null);

            // 3) Order + OrderItem yaz.
            $order = Order::create([
                'order_no'       => $this->generateOrderNo(),
                'user_id'        => $userId,
                'tenant_id'      => $tenant->id,
                'order_type'     => Order::TYPE_DROPSHIP,
                'shipping_info'  => [
                    'address'         => $data['address'] ?? null,
                    'shipping_method' => $shippingMethod,
                    'billing'         => $data['billing'] ?? null,
                    'promo_code'      => $serverTotals['promo_code'] ?? null,
                    'billing_to'      => $data['billing_to'] ?? null,
                ],
                'payment_method' => $data['payment_method'] ?? null,
                'note'           => $data['note'] ?? null,
                'subtotal'       => $serverTotals['subtotal'],
                'shipping_fee'   => $serverTotals['shipping_fee'],
                'total'          => $serverTotals['total'],
                'status'         => 'pending',
            ]);

            foreach ($lines as $line) {
                $line['order_id'] = $order->id;
                OrderItem::create($line);
            }

            // 4) Kredi çekiminden ÖNCE stok sert düşümü (D1/D2). Yetersizse rollback.
            $this->stock->decrementForOrder($order, $order->items()->get());

            // 5) Kredi çek (lock altında yeniden doğrulanır).
            $this->credit->charge(
                tenant: $tenant,
                amount: (float) $serverTotals['total'],
                reason: 'order',
                orderId: $order->id,
            );

            // 6) Sepeti temizle.
            CartItem::query()->where('user_id', $userId)->delete();

            return $order;
        });
    }

    /**
     * Ürünün kapak görselinin HAM path'i (is_cover önceliği, sonra sort_order).
     * DB'de ham path saklanır; OrderItem::product_image_url accessor'ı URL'e çevirir.
     */
    private function coverImagePath(?int $productId): ?string
    {
        if ($productId === null) {
            return null;
        }

        return ProductImage::query()
            ->where('product_id', $productId)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->value('path');
    }

    public function generateOrderNo(): string
    {
        return 'SIP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
