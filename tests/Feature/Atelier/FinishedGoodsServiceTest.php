<?php

namespace Tests\Feature\Atelier;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Atelier\Services\FinishedGoodsService;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Tests\TestCase;

class FinishedGoodsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_order_writes_stock_and_movement_per_variant(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $variant = $product->variants()->create(['sku' => 'V-' . uniqid(), 'size' => '2', 'color_name' => 'Mavi', 'price' => 10, 'stock' => 0, 'sort_order' => 0]);
        $warehouse = Warehouse::create(['name' => 'Ana', 'code' => 'W-' . uniqid()]);

        $order = ProductionOrder::create([
            'code' => 'IE-' . uniqid(), 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'status' => ProductionOrder::STATUS_IN_PROGRESS, 'planned_qty' => 50,
        ]);
        $order->items()->create(['product_variant_id' => $variant->id, 'planned_qty' => 50, 'produced_qty' => 48]);

        app(FinishedGoodsService::class)->receiveIntoStock($order);

        $this->assertDatabaseHas('stocks', [
            'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id, 'quantity' => 48,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id, 'warehouse_id' => $warehouse->id,
            'type' => StockMovement::TYPE_IN, 'quantity' => 48,
            'reference_type' => ProductionOrder::class, 'reference_id' => $order->id,
        ]);
        $this->assertSame(48, (int) $variant->fresh()->stock);
    }
}
