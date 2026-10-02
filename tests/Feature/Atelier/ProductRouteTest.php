<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\FasonSupplier;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Models\ProductRouteStep;
use Modules\Atelier\Services\ProductRouteService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün rotası: yeni iş emrinin aşamaları ürünün rotasından otomatik dolar; rota yoksa
 * operasyonlar sort_order sırasıyla önerilir. İş emri rotanın kopyasını taşır.
 */
class ProductRouteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['atelier.view', 'atelier.production.manage', 'atelier.bom.manage', 'product.add'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo(['atelier.view', 'atelier.production.manage', 'atelier.bom.manage', 'product.add']);
    }

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        // sort_order kasıtlı olarak id sırasından farklı
        $paket = Operation::create(['code' => 'paket', 'name' => 'Paket', 'default_location' => 'in_house', 'default_unit_cost' => 0.5, 'sort_order' => 30]);
        $kesim = Operation::create(['code' => 'kesim', 'name' => 'Kesim', 'default_location' => 'in_house', 'default_unit_cost' => 1, 'sort_order' => 10]);
        $dikim = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason', 'default_unit_cost' => 5, 'sort_order' => 20]);
        $fason = FasonSupplier::create(['name' => 'Fasoncu A']);

        return compact('product', 'variant', 'warehouse', 'kesim', 'dikim', 'paket', 'fason');
    }

    private function orderPayload(array $s, array $extra = []): array
    {
        return array_merge([
            'product_id'   => $s['product']->id,
            'warehouse_id' => $s['warehouse']->id,
            'planned_qty'  => 10,
            'items'        => [['product_variant_id' => $s['variant']->id, 'planned_qty' => 10]],
            'steps'        => [
                ['operation_id' => $s['kesim']->id, 'sequence' => 1, 'location_type' => 'in_house', 'unit_cost' => 1],
                ['operation_id' => $s['dikim']->id, 'sequence' => 2, 'location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id, 'unit_cost' => 12],
            ],
        ], $extra);
    }

    public function test_product_without_route_gets_operations_in_sort_order(): void
    {
        $s = $this->scenario();

        $route = $this->actingAs($this->admin)
            ->getJson("/atelier/production-orders/plan-preview?product_id={$s['product']->id}&planned_qty=10")
            ->assertOk()
            ->json('route');

        $this->assertSame(ProductRouteService::SOURCE_SUGGESTED, $route['source']);
        $this->assertSame([$s['kesim']->id, $s['dikim']->id, $s['paket']->id], array_column($route['steps'], 'operation_id'));
        $this->assertSame('fason', $route['steps'][1]['location_type']);
        $this->assertEquals(5, $route['steps'][1]['unit_cost']);
    }

    public function test_order_can_save_its_route_as_product_route(): void
    {
        $s = $this->scenario();

        $this->actingAs($this->admin)
            ->post('/atelier/production-orders', $this->orderPayload($s, ['save_as_product_route' => true]))
            ->assertSessionHasNoErrors();

        $route = $this->getJson("/atelier/production-orders/plan-preview?product_id={$s['product']->id}&planned_qty=10")->json('route');

        $this->assertSame(ProductRouteService::SOURCE_PRODUCT, $route['source']);
        $this->assertCount(2, $route['steps']);
        $this->assertSame($s['fason']->id, $route['steps'][1]['fason_supplier_id']);
        $this->assertEquals(12, $route['steps'][1]['unit_cost']);
    }

    public function test_save_flag_is_ignored_without_bom_permission(): void
    {
        $s = $this->scenario();
        $planner = User::factory()->create();
        $planner->givePermissionTo(['atelier.view', 'atelier.production.manage']);

        $this->actingAs($planner)
            ->post('/atelier/production-orders', $this->orderPayload($s, ['save_as_product_route' => true]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ProductionOrder::count());
        $this->assertSame(0, ProductRouteStep::count());
    }

    public function test_changing_product_route_does_not_touch_existing_orders(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->admin)->post('/atelier/production-orders', $this->orderPayload($s, ['save_as_product_route' => true]));
        $order = ProductionOrder::firstOrFail();

        $this->put("/atelier/products/{$s['product']->id}/route", [
            'steps' => [['operation_id' => $s['paket']->id, 'sequence' => 1, 'location_type' => 'in_house', 'unit_cost' => 0]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ProductRouteStep::where('product_id', $s['product']->id)->count());
        $this->assertSame(2, $order->steps()->count());
    }

    public function test_route_update_validates_fason_supplier_and_permission(): void
    {
        $s = $this->scenario();
        $url = "/atelier/products/{$s['product']->id}/route";
        $steps = ['steps' => [['operation_id' => $s['dikim']->id, 'sequence' => 1, 'location_type' => 'fason']]];

        $this->actingAs($this->admin)->put($url, $steps)->assertSessionHasErrors('steps.0.fason_supplier_id');

        $planner = User::factory()->create();
        $planner->givePermissionTo('atelier.production.manage');
        $this->actingAs($planner)->put($url, $steps)->assertForbidden();

        $this->assertSame(0, ProductRouteStep::count());
    }

    public function test_route_steps_are_renumbered_in_given_order(): void
    {
        $s = $this->scenario();

        app(ProductRouteService::class)->replace($s['product']->id, [
            ['operation_id' => $s['paket']->id, 'sequence' => 7, 'location_type' => 'in_house'],
            ['operation_id' => $s['kesim']->id, 'sequence' => 3, 'location_type' => 'in_house'],
        ]);

        $this->assertSame(
            [[1, $s['kesim']->id], [2, $s['paket']->id]],
            ProductRouteStep::orderBy('sequence')->get()->map(fn ($r) => [$r->sequence, $r->operation_id])->all(),
        );
    }

    public function test_development_product_can_be_created_with_route(): void
    {
        $s = $this->scenario();
        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 100, 'current_stock' => 1000]);

        $this->actingAs($this->admin)->post('/atelier/new-product', [
            'name' => 'Keten', 'sku' => 'KTN-R', 'category_id' => $s['product']->category_id, 'gender' => 'Kadın',
            'variants' => [['size' => 'M', 'color_name' => 'Ekru', 'sku' => 'KTN-R-M']],
            'lines'    => [['material_id' => $kumas->id, 'quantity_per_unit' => 1.5]],
            'price'    => 300,
            'route'    => [
                ['operation_id' => $s['kesim']->id, 'sequence' => 1, 'location_type' => 'in_house', 'unit_cost' => 2],
                ['operation_id' => $s['dikim']->id, 'sequence' => 2, 'location_type' => 'fason', 'fason_supplier_id' => $s['fason']->id, 'unit_cost' => 15],
            ],
        ])->assertSessionHasNoErrors();

        $product = Product::where('sku', 'KTN-R')->firstOrFail();
        $this->assertSame(2, ProductRouteStep::where('product_id', $product->id)->count());
    }
}
