<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Order;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Exceptions\InsufficientCreditException;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Tests\TestCase;

/**
 * Phase 2'de CheckoutController::store body'sini CheckoutService::place'e taşıdık.
 * Bu test refactor sonrası tenant + B2C davranışlarının korunduğunu doğrular.
 */
class CheckoutServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    private function cart(int $userId, int $qty = 1, float $price = 100): \Illuminate\Support\Collection
    {
        $pid = DB::table('products')->insertGetId([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => $price, 'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id' => $userId, 'product_id' => $pid, 'variant_id' => null,
            'qty' => $qty, 'price' => $price, 'color' => null, 'size' => null,
        ]);

        return CartItem::with('product')->where('user_id', $userId)->get();
    }

    public function test_b2c_path_writes_order_with_null_tenant(): void
    {
        $user = User::factory()->create(['tenant_id' => null]);
        $items = $this->cart($user->id, 1, 100);
        $totals = ['subtotal' => 100, 'shipping_fee' => 0, 'total' => 100, 'promo_code' => null];

        $order = app(CheckoutService::class)->place(
            ['address' => [], 'shipping_method' => 'standard', 'payment_method' => 'bank'],
            $user->id,
            null,
            $items,
            $totals,
        );

        $this->assertNull($order->tenant_id);
        $this->assertSame('b2c', $order->order_type);
        $this->assertEquals(100, (float) $order->total);
        // Cart temizlendi.
        $this->assertSame(0, CartItem::where('user_id', $user->id)->count());
    }

    public function test_tenant_path_writes_order_and_charges_credit(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 5000, 'current_balance' => 0]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250);
        $totals = ['subtotal' => 500, 'shipping_fee' => 49.90, 'total' => 549.90, 'promo_code' => null];

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'standard'],
            $user->id,
            $tenant->id,
            $items,
            $totals,
        );

        $this->assertSame($tenant->id, $order->tenant_id);
        $this->assertSame('dropship', $order->order_type);
        $this->assertEqualsWithDelta(549.90, (float) $tenant->fresh()->current_balance, 0.01);
        $this->assertSame(1, TenantCreditLedger::where('tenant_id', $tenant->id)->where('order_id', $order->id)->count());
    }

    public function test_tenant_over_limit_throws(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 100, 'current_balance' => 50]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 1, 200);
        $totals = ['subtotal' => 200, 'shipping_fee' => 0, 'total' => 200, 'promo_code' => null];

        $this->expectException(InsufficientCreditException::class);
        app(CheckoutService::class)->place([], $user->id, $tenant->id, $items, $totals);

        // Order yazılmadı.
        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
    }
}
