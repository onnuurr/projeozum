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
use Modules\Product\Services\OrderService;
use Modules\Product\Services\StockService;
use Modules\Product\Services\VatRateService;
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
        private TenantAccessService $access,
        private StockService $stock,
        private TenantCreditService $credit,
        private OrderService $orders,
        private VatRateService $vat,
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
            [$lines, $subtotal, $taxAmount] = $this->buildLines($tenant, $data['items']);
            $total = round($subtotal + $taxAmount, 2);

            $order = Order::create([
                'order_no' => $this->orders->generateOrderNo(),
                'user_id' => $actorUserId,
                'tenant_id' => $tenant->id,
                'bagisto_order_id' => $bagistoOrderId,
                'order_type' => Order::TYPE_DROPSHIP,
                'shipping_info' => [
                    'source' => 'bagisto',
                    'shipping_method' => 'cargo',
                    // Dropship son alıcı varsa kargo ona gider; yoksa Bagisto teslimat adresi (bayinin kendisi).
                    'address' => $this->dropshipAddress($data['dropship'] ?? null) ?? ($data['shipping_address'] ?? null),
                    'dropship' => $data['dropship'] ?? null,
                ],
                'payment_method' => $this->paymentMethod($data['payment_method'] ?? null),
                'note' => 'Bagisto sipariş #'.($data['increment_id'] ?? $bagistoOrderId),
                'subtotal' => $subtotal,
                'shipping_fee' => 0,
                'discount_rate' => 0,
                'discount_amount' => 0,
                // subtotal = KDV hariç (matrah), tax_amount = KDV, total = bayinin ödediği KDV dahil tutar
                'tax_amount' => $taxAmount,
                'total' => $total,
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

            // Kartla peşin ödenen sipariş cari hesaba yazılmaz (bkz. Bagisto credit-limit.exempt_payment_methods).
            if (($data['charge_credit'] ?? true) !== false) {
                try {
                    $this->credit->charge(
                        tenant: $tenant,
                        amount: (float) $total,
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
            }

            return $order;
        });
    }

    /**
     * Bagisto'nun "dropship" bloğunu, shipping_address ile aynı anahtarlara sahip adres dizisine çevirir
     * (sipariş ekranları ve kargo entegrasyonu mevcut adres şemasını okumaya devam eder).
     */
    private function dropshipAddress(?array $dropship): ?array
    {
        if (empty($dropship['recipient_name'])) {
            return null;
        }

        $name = preg_split('/\s+/', trim($dropship['recipient_name']));
        $lastName = count($name) > 1 ? array_pop($name) : '';

        return [
            'first_name' => implode(' ', $name),
            'last_name' => $lastName,
            'company_name' => null,
            'address' => $dropship['address'] ?? null,
            'city' => $dropship['city'] ?? null,
            'state' => $dropship['district'] ?? null,
            'country' => 'TR',
            'postcode' => $dropship['postcode'] ?? null,
            'phone' => $dropship['recipient_phone'] ?? null,
        ];
    }

    /**
     * Bagisto ödeme yöntemi kodunu SaaS sipariş ekranındaki etiket anahtarına çevirir
     * (OrderDetail.vue PAYMENT_METHODS). Bilinmeyen kod olduğu gibi saklanır.
     */
    private function paymentMethod(?string $method): ?string
    {
        return match ($method) {
            null, '' => null,
            'iyzico' => 'card',
            'cari_hesap' => 'cari',
            'moneytransfer' => 'bank',
            'cashondelivery' => 'cod',
            default => mb_substr($method, 0, 32),
        };
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
     * (TenantAccessService::priceFor, KDV hariç) — tenant'tan çekilecek gerçek maliyet budur.
     * KDV satır bazında ürünün kategori oranıyla eklenir (VatRateService); oran satıra yazılır.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float}
     */
    private function buildLines(Tenant $tenant, array $items): array
    {
        $lines = [];
        $subtotal = 0.0;
        $taxAmount = 0.0;

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
            $lineTotal = round($unitPrice * $qty, 2);
            $vatRate = $this->vat->rateFor($product);
            $vatAmount = $this->vat->vatOf($lineTotal, $vatRate);
            $subtotal += $lineTotal;
            $taxAmount += $vatAmount;

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
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
            ];
        }

        return [$lines, round($subtotal, 2), round($taxAmount, 2)];
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
