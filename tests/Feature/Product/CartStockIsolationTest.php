<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\CartItem;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * D1: sepet stoğa ASLA dokunmaz. add/updateQty/remove/clear ne stocks ne de
 * product_variants.stock cache'ini değiştirir, hiç StockMovement yazmaz.
 */
class CartStockIsolationTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;
    private Stock $stock;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'portal.checkout', 'guard_name' => 'web']);

        $this->user = User::factory()->create(['tenant_id' => null, 'email_verified_at' => now()]);
        $this->user->givePermissionTo('portal.checkout');

        $product       = Product::factory()->create(['price' => 100]);
        $this->variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100, 'stock' => 50]);
        $wh            = Warehouse::factory()->default()->create();
        $this->stock   = Stock::factory()->create([
            'product_variant_id' => $this->variant->id, 'warehouse_id' => $wh->id, 'quantity' => 50,
        ]);
    }

    private function assertStockUntouched(): void
    {
        $this->assertSame(50, $this->stock->fresh()->quantity, 'stocks.quantity değişmemeli');
        $this->assertSame(50, (int) $this->variant->fresh()->stock, 'product_variants.stock cache değişmemeli');
        $this->assertSame(0, StockMovement::count(), 'sepet işlemi StockMovement yazmamalı');
    }

    public function test_add_does_not_touch_stock(): void
    {
        $this->actingAs($this->user)
            ->post(route('cart.add'), ['product_id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'qty' => 3])
            ->assertRedirect();

        $this->assertSame(1, CartItem::where('user_id', $this->user->id)->count());
        $this->assertStockUntouched();
    }

    public function test_update_qty_does_not_touch_stock(): void
    {
        $item = CartItem::create([
            'user_id' => $this->user->id, 'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->id, 'qty' => 2, 'price' => 100,
        ]);

        $this->actingAs($this->user)
            ->put(route('cart.update', $item), ['qty' => 9])
            ->assertRedirect();

        $this->assertSame(9, (int) $item->fresh()->qty);
        $this->assertStockUntouched();
    }

    public function test_remove_does_not_touch_stock(): void
    {
        $item = CartItem::create([
            'user_id' => $this->user->id, 'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->id, 'qty' => 4, 'price' => 100,
        ]);

        $this->actingAs($this->user)
            ->delete(route('cart.remove', $item))
            ->assertRedirect();

        $this->assertSame(0, CartItem::where('user_id', $this->user->id)->count());
        $this->assertStockUntouched();
    }

    public function test_clear_does_not_touch_stock(): void
    {
        CartItem::create([
            'user_id' => $this->user->id, 'product_id' => $this->variant->product_id,
            'variant_id' => $this->variant->id, 'qty' => 4, 'price' => 100,
        ]);

        $this->actingAs($this->user)
            ->delete(route('cart.clear'))
            ->assertRedirect();

        $this->assertSame(0, CartItem::where('user_id', $this->user->id)->count());
        $this->assertStockUntouched();
    }
}
