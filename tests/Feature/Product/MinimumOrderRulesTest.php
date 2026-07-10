<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Exceptions\MinimumOrderException;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Order;
use Modules\Product\Services\CheckoutService;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

/**
 * Faz 3: B2B min-sipariş kuralları — tenant `min_order_total` + ürün `min_order_qty` / `order_multiple`.
 * İhlalde MinimumOrderException fırlar (server otoritesi), hiçbir sipariş kalıcılaşmaz.
 */
class MinimumOrderRulesTest extends TestCase
{
    use RefreshDatabase;

    private function cart(int $userId, int $qty, float $price, array $productAttrs = []): \Illuminate\Support\Collection
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pid = DB::table('products')->insertGetId(array_merge([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => $price, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ], $productAttrs));
        CartItem::create([
            'user_id' => $userId, 'product_id' => $pid, 'variant_id' => null,
            'qty' => $qty, 'price' => $price, 'color' => null, 'size' => null,
        ]);

        return CartItem::with('product')->where('user_id', $userId)->get();
    }

    private function place(Tenant $tenant, User $user, $items): Order
    {
        return app(CheckoutService::class)->place(
            ['shipping_method' => 'cargo'], $user->id, $tenant->id, $items, [],
        );
    }

    public function test_tenant_min_order_total_violation_throws(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0,
            'discount_rate' => 0, 'min_order_total' => 1000,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250); // ara toplam 500 < 1000

        try {
            $this->place($tenant, $user, $items);
            $this->fail('MinimumOrderException bekleniyordu.');
        } catch (MinimumOrderException $e) {
            $this->assertSame(MinimumOrderException::TYPE_TENANT_TOTAL, $e->type);
        }

        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
    }

    public function test_line_min_order_qty_violation_throws(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0, 'discount_rate' => 0,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 2, 250, ['min_order_qty' => 10]); // 2 < 10

        try {
            $this->place($tenant, $user, $items);
            $this->fail('MinimumOrderException bekleniyordu.');
        } catch (MinimumOrderException $e) {
            $this->assertSame(MinimumOrderException::TYPE_LINE_QTY, $e->type);
        }

        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
    }

    public function test_line_order_multiple_violation_throws(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0, 'discount_rate' => 0,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        $items = $this->cart($user->id, 4, 250, ['order_multiple' => 6]); // 4 % 6 != 0

        try {
            $this->place($tenant, $user, $items);
            $this->fail('MinimumOrderException bekleniyordu.');
        } catch (MinimumOrderException $e) {
            $this->assertSame(MinimumOrderException::TYPE_LINE_MULTIPLE, $e->type);
        }

        $this->assertSame(0, Order::where('tenant_id', $tenant->id)->count());
    }

    public function test_valid_order_passes_all_constraints(): void
    {
        $tenant = Tenant::factory()->create([
            'credit_limit' => 5000, 'current_balance' => 0,
            'discount_rate' => 0, 'min_order_total' => 400,
        ]);
        $user  = User::factory()->create(['tenant_id' => $tenant->id]);
        // 12 x 50 = 600 (>=400), qty 12 >= 6, 12 % 6 == 0 → tüm kurallar geçer.
        $items = $this->cart($user->id, 12, 50, ['min_order_qty' => 6, 'order_multiple' => 6]);

        $order = $this->place($tenant, $user, $items);

        $this->assertNotNull($order->id);
        $this->assertSame(1, Order::where('tenant_id', $tenant->id)->count());
    }
}
