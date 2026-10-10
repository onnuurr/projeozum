<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\FasonSupplier;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductionOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => 'atelier.production.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['atelier.production.manage', 'atelier.view']);
    }

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);
        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'current_stock' => 1000]);
        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 0]);
        $op = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason']);
        $fason = FasonSupplier::create(['name' => 'Fasoncu A']);

        return compact('product', 'variant', 'warehouse', 'op', 'fason', 'kumas');
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
                'steps'        => [['operation_id' => $s['op']->id, 'sequence' => 1, 'location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id, 'unit_cost' => 2]],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order = ProductionOrder::first();
        $this->assertNotNull($order);
        $this->assertDatabaseHas('production_order_items', ['production_order_id' => $order->id, 'planned_qty' => 100]);
        $this->assertDatabaseHas('production_order_steps', ['production_order_id' => $order->id, 'operation_id' => $s['op']->id]);
        $this->assertDatabaseHas('production_step_decisions', ['fason_supplier_id' => $s['fason']->id, 'decided_by' => $this->admin->id]);
    }

    public function test_store_rejects_fason_step_without_supplier(): void
    {
        $s = $this->scenario();

        $this->actingAs($this->admin)
            ->post('/atelier/production-orders', [
                'product_id'   => $s['product']->id,
                'warehouse_id' => $s['warehouse']->id,
                'planned_qty'  => 10,
                'items'        => [['product_variant_id' => $s['variant']->id, 'planned_qty' => 10]],
                'steps'        => [['operation_id' => $s['op']->id, 'sequence' => 1, 'location_type' => 'fason']],
            ])
            ->assertSessionHasErrors('steps.0.fason_supplier_id');

        $this->assertDatabaseCount('production_orders', 0);
    }

    public function test_full_flow_through_http(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->admin)->post('/atelier/production-orders', [
            'product_id'   => $s['product']->id,
            'warehouse_id' => $s['warehouse']->id,
            'planned_qty'  => 5,
            'items'        => [['product_variant_id' => $s['variant']->id, 'planned_qty' => 5]],
            'steps'        => [['operation_id' => $s['op']->id, 'sequence' => 1, 'location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id, 'unit_cost' => 2]],
        ]);
        $order = ProductionOrder::firstOrFail();
        $step = $order->steps()->firstOrFail();

        $this->post("/atelier/production-orders/{$order->id}/plan")->assertSessionHasNoErrors();
        $this->post("/atelier/production-orders/{$order->id}/steps/{$step->id}/batches", ['good_qty' => 9])
            ->assertSessionHasErrors('batch');
        $this->post("/atelier/production-orders/{$order->id}/steps/{$step->id}/batches", ['good_qty' => 5])
            ->assertSessionHasNoErrors();
        $this->post("/atelier/production-orders/{$order->id}/receive", [
            'items' => [['product_variant_id' => $s['variant']->id, 'qty' => 5]],
        ])->assertSessionHasNoErrors();
        $this->post("/atelier/production-orders/{$order->id}/close")->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->status);
        $this->assertSame(5, $order->received_qty);
        $this->assertSame('995.000', $s['kumas']->fresh()->current_stock);

        $this->get("/atelier/production-orders/{$order->id}")->assertOk();
    }

    public function test_plan_action_reserves_materials(): void
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
