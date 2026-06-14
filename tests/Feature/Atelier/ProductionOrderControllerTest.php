<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);
        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'current_stock' => 1000]);
        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 0]);
        $op = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason']);

        return compact('product', 'variant', 'warehouse', 'op');
    }

    public function test_store_creates_order_with_items_and_steps(): void
    {
        $s = $this->scenario();

        $this->actingAs($this->admin)
            ->post('/atelier/production-orders', [
                'product_id'   => $s['product']->id,
                'warehouse_id' => $s['warehouse']->id,
                'planned_qty'  => 100,
                'items'        => [['product_variant_id' => $s['variant']->id, 'planned_qty' => 100]],
                'steps'        => [['operation_id' => $s['op']->id, 'sequence' => 1, 'location_type' => 'fason', 'unit_cost' => 2]],
            ])
            ->assertRedirect();

        $order = ProductionOrder::first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('production_order_items', ['production_order_id' => $order->id, 'planned_qty' => 100]);
        $this->assertDatabaseHas('production_order_steps', ['production_order_id' => $order->id, 'operation_id' => $s['op']->id]);
    }

    public function test_plan_action_consumes_materials(): void
    {
        $s = $this->scenario();
        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $s['product']->id, 'warehouse_id' => $s['warehouse']->id,
            'status' => ProductionOrder::STATUS_DRAFT, 'planned_qty' => 10,
        ]);
        $order->items()->create(['product_variant_id' => $s['variant']->id, 'planned_qty' => 10]);

        $this->actingAs($this->admin)
            ->post("/atelier/production-orders/{$order->id}/plan")
            ->assertRedirect();

        $this->assertSame(ProductionOrder::STATUS_PLANNED, $order->fresh()->status);
    }
}
