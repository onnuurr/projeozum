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
        $this->assertEquals(160.0, (float) $order->total);

        // Kredi çekimi tenant'ın gerçek toptan fiyatı üzerinden yapılmalı (80 * 2), Bagisto'nun
        // gönderdiği perakende fiyatı değil.
        $this->assertEquals(160.0, (float) $tenant->fresh()->current_balance);
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
