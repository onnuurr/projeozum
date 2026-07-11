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
use Modules\Product\Exceptions\MinimumOrderException;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;
use Modules\Tenant\Services\TenantCreditService;

/**
 * Checkout transaction gövdesi — portal (dropship) controller bu service'i çağırır.
 *
 * D4: sepet fiyatı (cart_items.price) yalnız görüntü amaçlıdır; place() her satırı
 * transaction içinde TenantAccessService::priceFor() ile YENİDEN fiyatlar. Sipariş
 * düzeyi iskonto (tenant.discount_rate) ara toplama uygulanır; satır fiyat provenance'ı
 * temiz kalır. D6: kargo `cargo`/`pickup` — config'ten sabit ücret, eşik üstü ücretsiz.
 * Stok, kredi çekiminden ÖNCE StockService ile sert düşülür.
 */
class CheckoutService
{
    public function __construct(
        private TenantCreditService $credit,
        private TenantAccessService $access,
        private StockService $stock,
    ) {}

    /**
     * Görüntü amaçlı fiyat teklifi: yeniden-fiyatlı ara toplam + iskonto + kargo + toplam.
     * place() ile aynı fiyatlama kurallarını paylaşır (tek kaynak).
     *
     * @return array{subtotal: float, discount_rate: float, discount_amount: float, shipping_fee: float, total: float}
     */
    public function quote(Tenant $tenant, iterable $items, string $shippingMethod): array
    {
        [, $subtotal] = $this->buildLines($tenant, $items);

        return $this->totalsFor($tenant, $subtotal, $shippingMethod);
    }

    /**
     * Sipariş kesimi. Fiyatlar yeniden çözülür, kapak görseli snapshot'lanır, min-sipariş
     * kuralları doğrulanır, stok sert düşülür ve kredi çekilir — hepsi tek transaction içinde.
     *
     * @param  array     $data      Validated checkout payload (address, shipping_method, ...).
     * @param  int       $userId    Auth user id.
     * @param  int       $tenantId  Sipariş sahibi tenant (tenant-only B2B — zorunlu).
     * @param  iterable  $items     Sepete yüklü CartItem koleksiyonu.
     * @param  array     $totals    Görüntü amaçlı toplam (para için GÜVENİLMEZ; server yeniden hesaplar).
     *
     * @throws MinimumOrderException        min sipariş tutarı/adedi/koli katı ihlali.
     * @throws InsufficientCreditException  tenant kredisi yetmiyorsa.
     * @throws InsufficientStockException   stok yetmiyorsa.
     */
    public function place(array $data, int $userId, int $tenantId, iterable $items, array $totals): Order
    {
        $tenant = Tenant::with('type')->findOrFail($tenantId);

        return DB::transaction(function () use ($userId, $tenant, $items, $data) {
            $shippingMethod = $data['shipping_method'] ?? 'cargo';

            // 1) Satırları yeniden fiyatla + kapak görselini snapshot'la.
            [$lines, $subtotal] = $this->buildLines($tenant, $items);

            // 2) B2B min-sipariş kurallarını doğrula (server otoritesi).
            $this->assertOrderConstraints($tenant, $lines, $subtotal);

            // 3) Toplamları sunucuda yeniden-fiyatlanan satırlardan hesapla.
            $serverTotals = $this->totalsFor($tenant, $subtotal, $shippingMethod);

            // 4) Order + OrderItem yaz.
            $order = Order::create([
                'order_no'        => $this->generateOrderNo(),
                'user_id'         => $userId,
                'tenant_id'       => $tenant->id,
                'order_type'      => Order::TYPE_DROPSHIP,
                // Kargo firması + bayinin anlaşmalı kargo müşteri kodu (checkout'ta zorunlu).
                'carrier_id'          => $data['carrier_id'] ?? null,
                'cargo_customer_code' => $data['cargo_customer_code'] ?? null,
                'shipping_info'   => [
                    'address'         => $data['address'] ?? null,
                    'shipping_method' => $shippingMethod,
                    'billing'         => $data['billing'] ?? null,
                    'billing_to'      => $data['billing_to'] ?? null,
                ],
                'payment_method'  => $data['payment_method'] ?? null,
                'note'            => $data['note'] ?? null,
                'subtotal'        => $serverTotals['subtotal'],
                'shipping_fee'    => $serverTotals['shipping_fee'],
                'discount_rate'   => $serverTotals['discount_rate'],
                'discount_amount' => $serverTotals['discount_amount'],
                'total'           => $serverTotals['total'],
                'due_date'        => $tenant->payment_term_days > 0
                    ? now()->addDays((int) $tenant->payment_term_days)->toDateString()
                    : null,
                'status'          => Order::STATUS_PENDING,
            ]);

            foreach ($lines as $line) {
                $line['order_id'] = $order->id;
                unset($line['min_order_qty'], $line['order_multiple']); // iç kullanım — kalıcılaştırma
                OrderItem::create($line);
            }

            // 5) Kredi çekiminden ÖNCE stok sert düşümü (D1/D2). Yetersizse rollback.
            $this->stock->decrementForOrder($order, $order->items()->get());

            // 6) Kredi çek — iskontolu total üstünden (lock altında yeniden doğrulanır).
            $this->credit->charge(
                tenant: $tenant,
                amount: (float) $serverTotals['total'],
                reason: 'order',
                orderId: $order->id,
            );

            // 7) Sepeti temizle.
            CartItem::query()->where('user_id', $userId)->delete();

            return $order;
        });
    }

    /**
     * Sepet kalemlerini yeniden fiyatlar; OrderItem'a yazılacak satırları ve ara toplamı döndürür.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildLines(Tenant $tenant, iterable $items): array
    {
        $lines    = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $product = Product::with('brand')->find($item->product_id);
            $variant = $item->variant_id ? ProductVariant::find($item->variant_id) : null;

            $unitPrice = $product
                ? $this->access->priceFor($tenant, $product, $variant)
                : (float) $item->price;

            $qty        = (int) $item->qty;
            $lineTotal  = $unitPrice * $qty;
            $subtotal  += $lineTotal;

            $lines[] = [
                'order_id'           => null, // place() dolduracak
                'product_id'         => $item->product_id,
                'product_variant_id' => $item->variant_id,
                'product_name'       => $product?->name ?? 'Ürün',
                'product_brand'      => $product?->brand?->name,
                'product_image'      => $this->coverImagePath($item->product_id),
                'color'              => $item->color,
                'size'               => $item->size,
                'qty'                => $qty,
                'unit_price'         => $unitPrice,
                'total_price'        => $lineTotal,
                // İç kullanım — min-sipariş kuralları için taşınır, OrderItem::create'de yok sayılır.
                'min_order_qty'      => $product?->min_order_qty,
                'order_multiple'     => $product?->order_multiple,
            ];
        }

        return [$lines, $subtotal];
    }

    /**
     * Ara toplamdan iskonto + kargo uygulayarak toplamları üretir.
     *
     * @return array{subtotal: float, discount_rate: float, discount_amount: float, shipping_fee: float, total: float}
     */
    private function totalsFor(Tenant $tenant, float $subtotal, string $shippingMethod): array
    {
        $discountRate   = (float) $tenant->discount_rate;
        $discountAmount = round($subtotal * $discountRate / 100, 2);
        $shippingFee    = $this->shippingFee($subtotal, $shippingMethod);

        return [
            'subtotal'        => round($subtotal, 2),
            'discount_rate'   => $discountRate,
            'discount_amount' => $discountAmount,
            'shipping_fee'    => $shippingFee,
            'total'           => max(0.0, round($subtotal - $discountAmount + $shippingFee, 2)),
        ];
    }

    /**
     * D6 kargo ücreti: `pickup` her zaman 0; `cargo` (ve legacy varsayılan) config'ten
     * sabit ücret, ara toplam eşiği aşarsa ücretsiz.
     */
    private function shippingFee(float $subtotal, string $shippingMethod): float
    {
        if ($shippingMethod === 'pickup') {
            return 0.0;
        }

        $fee    = (float) config('product.shipping.cargo_fee', 49.90);
        $target = (float) config('product.shipping.free_shipping_target', 500.00);

        return $subtotal >= $target ? 0.0 : $fee;
    }

    /**
     * B2B min-sipariş kuralları: tenant ara toplam eşiği + satır bazında adet/koli katı.
     *
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws MinimumOrderException
     */
    private function assertOrderConstraints(Tenant $tenant, array $lines, float $subtotal): void
    {
        $minTotal = $tenant->min_order_total;
        if ($minTotal !== null && $subtotal < (float) $minTotal) {
            throw MinimumOrderException::tenantTotal((float) $minTotal, $subtotal);
        }

        foreach ($lines as $line) {
            $qty  = (int) $line['qty'];
            $minQ = $line['min_order_qty'] ?? null;
            $mult = $line['order_multiple'] ?? null;

            if ($minQ !== null && $qty < (int) $minQ) {
                throw MinimumOrderException::lineQty((int) $line['product_id'], $line['product_name'] ?? null, (int) $minQ, $qty);
            }
            if ($mult !== null && (int) $mult > 0 && $qty % (int) $mult !== 0) {
                throw MinimumOrderException::lineMultiple((int) $line['product_id'], $line['product_name'] ?? null, (int) $mult, $qty);
            }
        }
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
