<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Carrier;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Carrier $carrier;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['portal.access', 'portal.checkout', 'portal.orders.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.checkout', 'portal.orders.view']);

        $this->carrier = Carrier::create([
            'code' => 'ARAS', 'name' => 'Aras Kargo', 'is_active' => true, 'sort_order' => 10,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'billing_to'          => 'us',
            'shipping_method'     => 'cargo',
            'carrier_id'          => $this->carrier->id,
            'cargo_customer_code' => 'MK-12345',
            'terms_accepted'      => true,
            'address' => [
                'name'   => 'Bayi Test',
                'phone'  => '+90 555 555 55 55',
                'street' => 'Test Mah. 1',
                'city'   => 'İstanbul',
            ],
        ], $overrides);
    }

    public function test_portal_checkout_creates_dropship_order_and_charges_credit(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'           => 'co-' . uniqid(),
            'credit_limit'   => 10000,
            'current_balance'=> 0,
            'discount_rate'  => 0, // sipariş düzeyi iskonto testte sabit total bekliyor
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Bluz', 'slug' => 'bluz-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 150, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id' => $user->id, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 2, 'price' => 150, 'color' => null, 'size' => null,
        ]);

        $this->actingAs($user)
            ->post("http://{$tenant->slug}.bizimsite.test/checkout", $this->payload())
            ->assertRedirect();

        $order = Order::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($order);
        $this->assertSame('dropship', $order->order_type);
        $this->assertEqualsWithDelta(349.90, (float) $order->total, 0.01); // 300 + 49.90

        // Kargo firması + müşteri kodu siparişe kalıcılaştırıldı.
        $this->assertSame($this->carrier->id, (int) $order->carrier_id);
        $this->assertSame('MK-12345', $order->cargo_customer_code);

        $this->assertEqualsWithDelta(349.90, (float) $tenant->fresh()->current_balance, 0.01);
        $this->assertSame(1, TenantCreditLedger::where('tenant_id', $tenant->id)->where('type', 'debit')->count());
    }

    public function test_portal_checkout_blocked_when_credit_insufficient(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'            => 'co2-' . uniqid(),
            'credit_limit'    => 200,
            'current_balance' => 180, // 20 ₺ kullanılabilir
            'discount_rate'   => 0,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Pahalı', 'slug' => 'p-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 500, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id' => $user->id, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 1, 'price' => 500, 'color' => null, 'size' => null,
        ]);

        $this->actingAs($user)
            ->post("http://{$tenant->slug}.bizimsite.test/checkout", $this->payload())
            ->assertRedirect();

        // Sipariş açılmadı, ledger yazılmadı.
        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, TenantCreditLedger::where('tenant_id', $tenant->id)->count());
        $this->assertEqualsWithDelta(180.0, (float) $tenant->fresh()->current_balance, 0.01);
    }

    public function test_portal_checkout_requires_carrier_and_cargo_code(): void
    {
        $tenant = Tenant::factory()->create([
            'slug'            => 'co3-' . uniqid(),
            'credit_limit'    => 10000,
            'current_balance' => 0,
            'discount_rate'   => 0,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'name' => 'Bluz', 'slug' => 'bluz-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 150, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id' => $user->id, 'product_id' => $productId, 'variant_id' => null,
            'qty' => 2, 'price' => 150, 'color' => null, 'size' => null,
        ]);

        // Kargo firması + kod olmadan → doğrulama hatası, sipariş açılmaz.
        $this->actingAs($user)
            ->post("http://{$tenant->slug}.bizimsite.test/checkout", $this->payload([
                'carrier_id'          => null,
                'cargo_customer_code' => '',
            ]))
            ->assertSessionHasErrors(['carrier_id', 'cargo_customer_code']);

        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());

        // Pasif kargo firması da reddedilir.
        $inactive = Carrier::create([
            'code' => 'PASIF', 'name' => 'Pasif Kargo', 'is_active' => false, 'sort_order' => 99,
        ]);
        $this->actingAs($user)
            ->post("http://{$tenant->slug}.bizimsite.test/checkout", $this->payload([
                'carrier_id' => $inactive->id,
            ]))
            ->assertSessionHasErrors(['carrier_id']);

        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
    }
}
