<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Atelier\Models\Operation;
use Modules\Atelier\Models\ProductBom;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Services\ProductionOrderService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Warehouse;
use Tests\TestCase;

class ProductionOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        $kumas = Material::create(['code' => 'K-' . uniqid(), 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10, 'current_stock' => 1000]);
        $bom = ProductBom::create(['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $bom->lines()->create(['material_id' => $kumas->id, 'quantity_per_unit' => 1.0, 'waste_pct' => 0]);

        return compact('product', 'variant', 'warehouse', 'kumas');
    }

    private function draftOrder(array $s, int $qty = 100): ProductionOrder
    {
        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $s['product']->id, 'warehouse_id' => $s['warehouse']->id,
            'status' => ProductionOrder::STATUS_DRAFT, 'planned_qty' => $qty,
        ]);
        $order->items()->create(['product_variant_id' => $s['variant']->id, 'planned_qty' => $qty]);

        return $order;
    }

    public function test_plan_consumes_materials_and_sets_material_cost(): void
    {
        $s = $this->scenario();
        $order = $this->draftOrder($s, 100);

        app(ProductionOrderService::class)->plan($order);

        // 100 adet * 1m = 100m tüketim; stok 1000 -> 900
        $this->assertSame('900.000', $s['kumas']->fresh()->current_stock);
        $this->assertDatabaseHas('material_movements', [
            'material_id' => $s['kumas']->id, 'reason' => 'consume', 'production_order_id' => $order->id,
        ]);
        // malzeme maliyeti = 100m * 10 = 1000
        $this->assertSame('1000.00', $order->fresh()->material_cost);
        $this->assertSame(ProductionOrder::STATUS_PLANNED, $order->fresh()->status);
    }

    public function test_plan_fails_when_material_stock_insufficient(): void
    {
        $s = $this->scenario();
        $s['kumas']->update(['current_stock' => 50]);
        $order = $this->draftOrder($s, 100); // 100m gerek, 50m var

        try {
            app(ProductionOrderService::class)->plan($order);
            $this->fail('Yetersiz hammadde istisnasi beklenmisti.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Yetersiz hammadde', $e->getMessage());
        }

        $this->assertSame('50.000', $s['kumas']->fresh()->current_stock);
        $this->assertDatabaseMissing('material_movements', ['production_order_id' => $order->id]);
    }

    public function test_complete_rolls_up_cost_and_receives_stock(): void
    {
        $s = $this->scenario();
        $order = $this->draftOrder($s, 100);
        app(ProductionOrderService::class)->plan($order);

        // bir fason adımı tamamlanmış gibi step ekle
        $op = Operation::create(['code' => 'dikim', 'name' => 'Dikim', 'default_location' => 'fason']);
        $order->steps()->create([
            'operation_id' => $op->id, 'sequence' => 1, 'location_type' => 'fason',
            'status' => 'done', 'input_qty' => 100, 'output_qty' => 98, 'unit_cost' => 2, 'step_cost' => 196,
        ]);
        $order->items()->first()->update(['produced_qty' => 98]);
        $order->update(['status' => ProductionOrder::STATUS_IN_PROGRESS]);

        app(ProductionOrderService::class)->complete($order->fresh());

        $fresh = $order->fresh();
        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $fresh->status);
        $this->assertSame(98, $fresh->produced_qty);
        $this->assertSame('196.00', $fresh->fason_cost);
        // total = material 1000 + fason 196 + labor 0 = 1196; unit = 1196/98 = 12.20
        $this->assertSame('1196.00', $fresh->total_cost);
        $this->assertSame('12.20', $fresh->unit_cost);
        // stok girişi yapıldı
        $this->assertDatabaseHas('stocks', ['product_variant_id' => $s['variant']->id, 'quantity' => 98]);
    }
}
