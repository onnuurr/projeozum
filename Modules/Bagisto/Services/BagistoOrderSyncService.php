<?php

namespace Modules\Bagisto\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Product\Exceptions\InsufficientStockException;
use Modules\Product\Exceptions\InvalidOrderTransitionException;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\CheckoutService;
use Modules\Product\Services\OrderService;
use Modules\Product\Services\StockService;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantAccessService;
use Modules\Tenant\Services\TenantCreditService;
use Throwable;

/**
 * Bagisto webhook'undan gelen sipariş verisini SaaS tarafında dropship
 * siparişine çevirir (bkz. OrderWebhookController sınıf-seviyesi tasarım
 * notu). Satış Bagisto'da zaten gerçekleşmiş ve geri alınamaz olduğu için,
 * stok/kredi yetersizliği siparişin kaydını ENGELLEMEZ — sadece loglanır.
 */
class BagistoOrderSyncService
{
    public function __construct(
        private CheckoutService $checkout,
        private TenantAccessService $access,
        private StockService $stock,
        private TenantCreditService $credit,
        private OrderService $orders,
    ) {}

    public function findByBagistoOrderId(string $bagistoOrderId): ?Order
    {
        return Order::where('bagisto_order_id', $bagistoOrderId)->first();
    }

    /**
     * Bagisto müşteri hesabının e-postasıdır (owner'ın portal giriş e-postası,
     * Tenant.email — iş/fatura iletişim alanı — DEĞİL). `owner()` yerine düz
     * `users()` kullanılır: `owner()`'ın `role('tenant')` filtresi bu sorguyu
     * gereksiz yere permission altyapısına bağımlı kılar.
     */
    public function resolveTenantForEmail(string $email): ?Tenant
    {
        return Tenant::whereHas('users', fn ($q) => $q->where('email', $email))->first();
    }

    public function createDropshipOrder(Tenant $tenant, int $actorUserId, string $bagistoOrderId, array $data): Order
    {
        return DB::transaction(function () use ($data, $tenant, $actorUserId, $bagistoOrderId) {
            [$lines, $subtotal] = $this->buildLines($tenant, $data['items']);

            $order = Order::create([
                'order_no' => $this->checkout->generateOrderNo(),
                'user_id' => $actorUserId,
                'tenant_id' => $tenant->id,
                'bagisto_order_id' => $bagistoOrderId,
                'order_type' => Order::TYPE_DROPSHIP,
                'shipping_info' => [
                    'source' => 'bagisto',
                    'shipping_method' => 'cargo',
                    'address' => $data['shipping_address'] ?? null,
                ],
                'note' => 'Bagisto sipariş #'.($data['increment_id'] ?? $bagistoOrderId),
                'subtotal' => $subtotal,
                'shipping_fee' => 0,
                'discount_rate' => 0,
                'discount_amount' => 0,
                'total' => $subtotal,
                'due_date' => $tenant->payment_term_days > 0
                    ? now()->addDays((int) $tenant->payment_term_days)->toDateString()
                    : null,
                'status' => Order::STATUS_PENDING,
            ]);

            foreach ($lines as $line) {
                $line['order_id'] = $order->id;
                OrderItem::create($line);
            }

            try {
                $this->stock->decrementForOrder($order, $order->items()->get());
            } catch (InsufficientStockException|Throwable $e) {
                Log::warning('Bagisto order webhook: stok düşümü başarısız, sipariş yine de kaydedildi.', [
                    'order_id' => $order->id,
                    'bagisto_order_id' => $bagistoOrderId,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $this->credit->charge(
                    tenant: $tenant,
                    amount: (float) $subtotal,
                    reason: 'bagisto_order',
                    orderId: $order->id,
                    byUserId: $actorUserId,
                );
            } catch (InsufficientCreditException $e) {
                Log::warning('Bagisto order webhook: kredi çekimi başarısız, sipariş yine de kaydedildi.', [
                    'order_id' => $order->id,
                    'bagisto_order_id' => $bagistoOrderId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order;
        });
    }

    /** Sipariş yoksa null döner (çağıran '404 değil, no-op' olarak ele alır). */
    public function cancelByBagistoOrderId(string $bagistoOrderId): ?Order
    {
        $order = $this->findByBagistoOrderId($bagistoOrderId);
        if (! $order) {
            return null;
        }

        try {
            $this->orders->transition($order, Order::STATUS_CANCELLED, $order->user, 'Bagisto tarafında iptal edildi.');
        } catch (InvalidOrderTransitionException $e) {
            Log::warning('Bagisto order webhook: iptal geçişi mevcut durumdan yapılamadı.', [
                'order_id' => $order->id,
                'status' => $order->status,
                'error' => $e->getMessage(),
            ]);
        }

        return $order;
    }

    /**
     * Bagisto'nun gönderdiği ham item satırlarını OrderItem payload'una çevirir.
     * Fiyat Bagisto'nun perakende fiyatı DEĞİL, tenant'ın toptan fiyatıdır
     * (TenantAccessService::priceFor) — tenant'tan çekilecek gerçek maliyet budur.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float}
     */
    private function buildLines(Tenant $tenant, array $items): array
    {
        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $variant = ProductVariant::with('product.brand')->where('sku', $item['sku'])->first();
            $product = $variant?->product;

            if (! $variant) {
                Log::warning('Bagisto order webhook: SKU eşleşmedi, satır ürünsüz kaydedildi.', ['sku' => $item['sku']]);
            }

            $unitPrice = $product
                ? $this->access->priceFor($tenant, $product, $variant)
                : (float) ($item['price'] ?? 0);

            $qty = (int) $item['qty'];
            $lineTotal = $unitPrice * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => $product?->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product?->name ?? $item['sku'],
                'product_brand' => $product?->brand?->name,
                'product_image' => $this->coverImagePath($product?->id),
                'color' => $variant?->color_name,
                'size' => $variant?->size,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal,
            ];
        }

        return [$lines, round($subtotal, 2)];
    }

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
}
