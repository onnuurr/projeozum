<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün formunda yeni varyanta girilen stok, varsayılan depoya açılış hareketi olarak yazılır
 * (Stok sayfası + sipariş tahsisi stocks tablosunu okur). Bkz. StockService::openingBalance().
 */
class OpeningStockTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $this->editor = User::factory()->create();
        $this->editor->givePermissionTo('product.add');

        $category = Category::create(['name' => 'Elbise', 'slug' => 'elbise-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Elbise', 'sku' => 'OS-1', 'price' => 100]);
    }

    private function saveForm(array $variants)
    {
        return $this->actingAs($this->editor)->put(route('products.update', $this->product), [
            'name' => 'Elbise', 'sku' => 'OS-1', 'category_id' => $this->product->category_id, 'variants' => $variants,
        ]);
    }

    public function test_new_variant_stock_goes_to_default_warehouse(): void
    {
        Warehouse::factory()->create(['is_active' => true, 'is_default' => false]);
        $default = Warehouse::factory()->create(['is_active' => true, 'is_default' => true]);

        $this->saveForm([['sku' => 'OS-1-M', 'price' => 100, 'stock' => 4, 'size' => 'M']])->assertSessionHasNoErrors();

        $variant = ProductVariant::where('sku', 'OS-1-M')->firstOrFail();
        $this->assertSame(4, (int) $variant->stock);
        $stock = Stock::where('product_variant_id', $variant->id)->sole();
        $this->assertSame($default->id, $stock->warehouse_id);
        $this->assertSame(4, $stock->quantity);
        $movement = StockMovement::where('product_variant_id', $variant->id)->sole();
        $this->assertSame(StockMovement::TYPE_IN, $movement->type);
        $this->assertSame(StockService::OPENING_NOTE, $movement->note);
    }

    public function test_without_active_warehouse_falls_back_to_variant_cache(): void
    {
        $this->saveForm([['sku' => 'OS-1-M', 'price' => 100, 'stock' => 2, 'size' => 'M']])->assertSessionHasNoErrors();

        $variant = ProductVariant::where('sku', 'OS-1-M')->firstOrFail();
        $this->assertSame(2, (int) $variant->stock);
        $this->assertSame(0, Stock::count());
    }

    public function test_zero_stock_writes_nothing(): void
    {
        Warehouse::factory()->create(['is_active' => true]);
        $this->saveForm([['sku' => 'OS-1-M', 'price' => 100, 'stock' => 0, 'size' => 'M']])->assertSessionHasNoErrors();

        $this->assertSame(0, Stock::count());
        $this->assertSame(0, StockMovement::count());
    }
}
