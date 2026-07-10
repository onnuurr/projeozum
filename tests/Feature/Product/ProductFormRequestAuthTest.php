<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Faz 4: inline validate() -> Form Request dönüşümleri. authorize() `->can()` üzerinden
 * (Gate — superadmin Gate::before bypass'ını respect eder), mevcut yetki string'leri korunur.
 */
class ProductFormRequestAuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    public function test_stock_movement_requires_permission(): void
    {
        $this->actingAs($this->user())
            ->post(route('products.stocks.movement'), [])
            ->assertForbidden();
    }

    public function test_stock_movement_succeeds_with_permission(): void
    {
        $variant   = ProductVariant::factory()->create(['stock' => 0]);
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($this->user(['stock.manage']))
            ->post(route('products.stocks.movement'), [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'type'               => StockMovement::TYPE_IN,
                'quantity'           => 7,
            ])
            ->assertRedirect(route('products.stocks.index'));

        $this->assertSame(7, (int) $variant->fresh()->stock);
    }

    public function test_stock_movement_validation_rejects_zero_quantity(): void
    {
        $variant   = ProductVariant::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($this->user(['stock.manage']))
            ->post(route('products.stocks.movement'), [
                'product_variant_id' => $variant->id,
                'warehouse_id'       => $warehouse->id,
                'type'               => StockMovement::TYPE_IN,
                'quantity'           => 0,
            ])
            ->assertSessionHasErrors('quantity');
    }

    public function test_brand_store_requires_permission(): void
    {
        $this->actingAs($this->user())
            ->post(route('products.brands.store'), ['name' => 'X', 'slug' => 'x'])
            ->assertForbidden();
    }

    public function test_brand_store_succeeds_with_permission(): void
    {
        $this->actingAs($this->user(['brand.manage']))
            ->post(route('products.brands.store'), [
                'name' => 'Marka', 'slug' => 'marka', 'sort_order' => 1,
            ])
            ->assertRedirect(route('products.brands.index'));

        $this->assertDatabaseHas('brands', ['slug' => 'marka']);
    }
}
