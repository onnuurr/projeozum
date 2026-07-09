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
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pid = DB::table('products')->insertGetId([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => $price, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CartItem::create([
            'user_id' => $userId, 'product_id' => $pid, 'variant_id' => null,
            'qty' => $qty, 'price' => $price, 'color' => null, 'size' => null,
        ]);

        return CartItem::with('product')->where('user_id', $userId)->get();
    }

    public function test_tenant_path_writes_order_and_charges_credit(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 5000, 'current_balance' => 0]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250);
        // $totals yalnız görüntü; place() server-side yeniden fiyatlar: 2 x 250 = 500 → ücretsiz kargo.
        $totals = ['subtotal' => 500, 'shipping_fee' => 0, 'total' => 500, 'promo_code' => null];

        $order = app(CheckoutService::class)->place(
            ['shipping_method' => 'standard'],
            $user->id,
            $tenant->id,
            $items,
            $totals,
        );

        $this->assertSame($tenant->id, $order->tenant_id);
        $this->assertSame('dropship', $order->order_type);
        $this->assertEqualsWithDelta(500.00, (float) $order->total, 0.01);
        $this->assertEqualsWithDelta(500.00, (float) $tenant->fresh()->current_balance, 0.01);
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
