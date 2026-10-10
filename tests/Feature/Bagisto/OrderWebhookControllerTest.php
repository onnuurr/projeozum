<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Faz 2: Bagisto'nun `PushEventToSaas` job'ının hedeflediği
 * `POST /api/webhooks/bagisto` endpoint'i. Bagisto müşteri hesabı = tenant
 * eşlemesi e-posta üzerinden yapılır (bkz. Webkul\SaasSync\TenantWebhookController).
 */
class OrderWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['bagisto.inbound.token' => 'test-token']);
    }

    private function postWebhook(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/webhooks/bagisto', $payload);
    }

    /**
     * Bagisto müşteri hesabının e-postası owner'ın (rol: tenant) e-postasıdır,
     * Tenant.email değil (bkz. TenantPayloadMapper) — $attributes['email'] o yüzden
     * owner'a uygulanır.
     */
    private function tenantWithUser(array $attributes = []): Tenant
    {
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        $ownerEmail = $attributes['email'] ?? 'magaza@example.com';
        unset($attributes['email']);

        $tenant = Tenant::factory()->create($attributes);

        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'email' => $ownerEmail]);
        $owner->assignRole('tenant');

        return $tenant;
    }

    public function test_rejects_request_without_valid_token(): void
    {
        $response = $this->postJson('/api/webhooks/bagisto', ['event' => 'order.created', 'data' => []]);

        $response->assertStatus(401);
    }

    public function test_order_created_records_dropship_order_and_charges_tenant_credit(): void
    {
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com', 'current_balance' => 0]);
        $product = Product::factory()->create(['price' => 100]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'VAR-1', 'price' => 80]);

        $response = $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 555,
                'increment_id' => '1000000555',
                'customer_email' => 'magaza@example.com',
                'grand_total' => 160,
                'items' => [
                    ['sku' => 'VAR-1', 'qty' => 2, 'price' => 80],
                ],
                'shipping_address' => [
                    'first_name' => 'Ali',
                    'last_name' => 'Veli',
                    'address1' => 'Test Sk. No:1',
                    'city' => 'İstanbul',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $order = Order::where('bagisto_order_id', '555')->first();
        $this->assertNotNull($order);
        $this->assertSame($tenant->id, $order->tenant_id);
        $this->assertSame(Order::TYPE_DROPSHIP, $order->order_type);
        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame(1, $order->items()->count());
        // Matrah = tenant'ın gerçek toptan fiyatı (80 * 2), Bagisto'nun gönderdiği perakende fiyatı değil;
        // KDV kategori oranıyla ayrı (kategori oranı yok → varsayılan %10), toplam KDV dahil.
        $this->assertEquals(160.0, (float) $order->subtotal);
        $this->assertEquals(16.0, (float) $order->tax_amount);
        $this->assertEquals(176.0, (float) $order->total);
        $this->assertEquals(10.0, (float) $order->items()->first()->vat_rate);

        // Kredi çekimi bayinin ödediği KDV dahil tutar üzerinden.
        $this->assertEquals(176.0, (float) $tenant->fresh()->current_balance);
    }

    public function test_order_created_charges_sales_price_minus_tenant_discount(): void
    {
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com', 'current_balance' => 0, 'discount_rate' => 30]);
        $product = Product::factory()->create(['price' => 100]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'VAR-1', 'price' => 80]);

        $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 556,
                'increment_id' => '1000000556',
                'customer_email' => 'magaza@example.com',
                'grand_total' => 112,
                // Bagisto bayiye müşteri grubu fiyatını (80 − %30 = 56) gösterir.
                'items' => [['sku' => 'VAR-1', 'qty' => 2, 'price' => 56]],
            ],
        ])->assertStatus(201);

        $order = Order::where('bagisto_order_id', '556')->firstOrFail();
        $this->assertEquals(56.0, (float) $order->items()->first()->unit_price);
        $this->assertEquals(112.0, (float) $order->subtotal);
        $this->assertEquals(123.2, (float) $order->total); // + %10 KDV
        $this->assertEquals(123.2, (float) $tenant->fresh()->current_balance);
    }

    public function test_order_created_ships_to_dropship_recipient_when_present(): void
    {
        $this->tenantWithUser(['email' => 'magaza@example.com']);
        $product = Product::factory()->create(['price' => 100]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'VAR-1', 'price' => 80]);

        $response = $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 777,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 1, 'price' => 80]],
                // Bayinin kendi (fatura) adresi — dropship varsa kargo buraya GİTMEMELİ.
                'shipping_address' => ['first_name' => 'Bayi', 'last_name' => 'Mağaza', 'city' => 'Ankara'],
                'dropship' => [
                    'recipient_name' => 'Zeynep Nur Kaya',
                    'recipient_phone' => '0532 555 44 33',
                    'address' => 'Lale Sok. No:14 D:5',
                    'district' => 'Kadıköy',
                    'city' => 'İstanbul',
                    'channel' => 'trendyol',
                    'channel_ref' => 'TY-948102941',
                    'label_url' => 'https://magaza.example.com/bayi/dropship-etiket/1?signature=abc',
                ],
            ],
        ]);

        $response->assertStatus(201);

        $info = Order::where('bagisto_order_id', '777')->first()->shipping_info;

        $this->assertSame('Zeynep Nur', $info['address']['first_name']);
        $this->assertSame('Kaya', $info['address']['last_name']);
        $this->assertSame('İstanbul', $info['address']['city']);
        $this->assertSame('Kadıköy', $info['address']['state']);
        $this->assertSame('0532 555 44 33', $info['address']['phone']);
        $this->assertSame('TY-948102941', $info['dropship']['channel_ref']);
    }

    public function test_order_created_accepts_cargo_only_dropship_and_keeps_dealer_address(): void
    {
        $this->tenantWithUser(['email' => 'magaza@example.com']);
        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80]);

        $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 779,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 1, 'price' => 80]],
                'shipping_address' => ['first_name' => 'Bayi', 'last_name' => 'Mağaza', 'city' => 'Ankara'],
                // Tek ekran checkout: son alıcı yok, yalnızca kargo firması + barkod no (+ isteğe bağlı PDF).
                'dropship' => [
                    'carrier' => 'yurtici',
                    'carrier_label' => 'Yurtiçi Kargo',
                    'tracking_number' => 'YK-1234567890',
                    'recipient_name' => null,
                    'label_url' => null,
                ],
            ],
        ])->assertStatus(201);

        $info = Order::where('bagisto_order_id', '779')->first()->shipping_info;

        $this->assertSame('Ankara', $info['address']['city']);
        $this->assertSame('yurtici', $info['dropship']['carrier']);
        $this->assertSame('YK-1234567890', $info['dropship']['tracking_number']);
    }

    public function test_card_paid_order_is_not_charged_to_credit_and_cancel_does_not_refund(): void
    {
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com', 'current_balance' => 0]);
        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80]);

        $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 780,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 2, 'price' => 80]],
                'payment_method' => 'iyzico',
                'charge_credit' => false,
            ],
        ])->assertStatus(201);

        $order = Order::where('bagisto_order_id', '780')->first();

        $this->assertSame('card', $order->payment_method);
        $this->assertEquals(0.0, (float) $tenant->fresh()->current_balance);

        $this->postWebhook(['event' => 'order.cancelled', 'data' => ['order_id' => 780]])->assertSuccessful();

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertEquals(0.0, (float) $tenant->fresh()->current_balance);
    }

    public function test_cari_paid_order_is_charged_and_labelled(): void
    {
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com', 'current_balance' => 0]);
        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80]);

        $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 781,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 2, 'price' => 80]],
                'payment_method' => 'cari_hesap',
                'charge_credit' => true,
            ],
        ])->assertStatus(201);

        $this->assertSame('cari', Order::where('bagisto_order_id', '781')->first()->payment_method);
        $this->assertGreaterThan(0.0, (float) $tenant->fresh()->current_balance);
    }

    public function test_order_created_rejects_incomplete_dropship_block(): void
    {
        $this->tenantWithUser(['email' => 'magaza@example.com']);

        $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 778,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 1]],
                'dropship' => ['recipient_name' => 'Eksik Adres'],
            ],
        ])->assertStatus(422);
    }

    public function test_order_created_is_idempotent_on_retry(): void
    {
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com']);
        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80]);

        $payload = [
            'event' => 'order.created',
            'data' => [
                'order_id' => 777,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 1]],
            ],
        ];

        $this->postWebhook($payload)->assertStatus(201);
        $this->postWebhook($payload)->assertStatus(200);

        $this->assertSame(1, Order::where('bagisto_order_id', '777')->count());
    }

    public function test_order_created_returns_404_when_tenant_not_found(): void
    {
        $response = $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 1,
                'customer_email' => 'unknown@example.com',
                'items' => [['sku' => 'VAR-X', 'qty' => 1]],
            ],
        ]);

        $response->assertStatus(404);
        $this->assertSame(0, Order::count());
    }

    public function test_order_created_still_records_order_when_stock_is_insufficient(): void
    {
        // ProductVariantFactory stock=0 üretir ve stocks tablosunda hiç satır yok;
        // "satış zaten gerçekleşti, kaydı engelleme" kararı bu senaryoyu kapsar.
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com']);
        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80, 'stock' => 0]);

        $response = $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 888,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 3]],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertSame(1, Order::where('bagisto_order_id', '888')->count());
    }

    public function test_order_cancelled_transitions_existing_order(): void
    {
        $tenant = $this->tenantWithUser(['email' => 'magaza@example.com']);
        ProductVariant::factory()->create(['sku' => 'VAR-1', 'price' => 80]);

        $this->postWebhook([
            'event' => 'order.created',
            'data' => [
                'order_id' => 999,
                'customer_email' => 'magaza@example.com',
                'items' => [['sku' => 'VAR-1', 'qty' => 1]],
            ],
        ])->assertStatus(201);

        $response = $this->postWebhook([
            'event' => 'order.cancelled',
            'data' => ['order_id' => 999],
        ]);

        $response->assertStatus(200);

        $order = Order::where('bagisto_order_id', '999')->first();
        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
    }

    public function test_order_cancelled_is_a_no_op_for_unknown_order(): void
    {
        $response = $this->postWebhook([
            'event' => 'order.cancelled',
            'data' => ['order_id' => 'does-not-exist'],
        ]);

        $response->assertStatus(200);
    }
}
