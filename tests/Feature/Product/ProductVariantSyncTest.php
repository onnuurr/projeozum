<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Models\Category;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;
use Modules\Product\Models\StockMovement;
use Modules\Product\Models\Warehouse;
use Modules\Product\Services\StockService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün düzenlemesinde varyantlar SKU ile eşleşip yerinde güncellenir (2026-09-24).
 * Eskiden sil-ve-yeniden-oluştur idi: her kayıt stocks / stock_movements / price_lists'i
 * CASCADE ile siliyor, üretim emrindeki varyant için RESTRICT'e çarpıyordu.
 */
class ProductVariantSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // StockChanged / Product* → Bagisto push'ları

        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $this->editor = User::factory()->create();
        $this->editor->givePermissionTo('product.add');

        $category = Category::create(['name' => 'Elbise', 'slug' => 'elbise-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Elbise', 'sku' => 'VS-1', 'gender' => 'Unisex', 'price' => 100,
        ]);
        $this->variant = $this->product->variants()->create(['sku' => 'VS-1-M', 'price' => 100, 'stock' => 0, 'size' => 'M']);
    }

    /** @param  array<int, array<string, mixed>>  $variants */
    private function saveForm(array $variants, string $sku = 'VS-1')
    {
        return $this->actingAs($this->editor)->put(route('products.update', $this->product), [
            'name' => 'Elbise', 'sku' => $sku, 'category_id' => $this->product->category_id,
            'gender' => 'Unisex', 'variants' => $variants,
        ]);
    }

    private function giveStock(ProductVariant $variant, int $qty): void
    {
        $warehouse = Warehouse::factory()->create();
        app(StockService::class)->move(
            variantId: $variant->id, warehouseId: $warehouse->id, type: StockMovement::TYPE_IN,
            qty: $qty, reference: null, note: null, userId: $this->editor->id,
        );
    }

    public function test_edit_keeps_variant_identity_stock_history_and_price_lists(): void
    {
        $this->giveStock($this->variant, 7);
        PriceList::create(['product_variant_id' => $this->variant->id, 'type' => PriceList::TYPE_DEALER, 'price' => 80, 'currency' => 'TRY', 'is_active' => true]);

        // Formdaki stok (999) mevcut varyant için yok sayılır — stok yalnız hareketle değişir.
        $this->saveForm([['sku' => 'VS-1-M', 'price' => 120, 'stock' => 999, 'size' => 'M']])->assertSessionHasNoErrors();

        $fresh = ProductVariant::find($this->variant->id);
        $this->assertNotNull($fresh, 'varyant kimliği korunmalı');
        $this->assertEquals(120, (float) $fresh->price);
        $this->assertSame(7, (int) $fresh->stock);
        $this->assertSame(7, (int) Stock::where('product_variant_id', $fresh->id)->sum('quantity'));
        $this->assertSame(1, StockMovement::where('product_variant_id', $fresh->id)->count());
        $this->assertSame(1, PriceList::where('product_variant_id', $fresh->id)->count());
    }

    public function test_adds_new_and_deletes_removed_variant_without_dependencies(): void
    {
        $this->saveForm([['sku' => 'VS-1-L', 'price' => 100, 'stock' => 3, 'size' => 'L']])->assertSessionHasNoErrors();

        $this->assertNull(ProductVariant::find($this->variant->id));
        $new = ProductVariant::where('sku', 'VS-1-L')->first();
        $this->assertNotNull($new);
        $this->assertSame(3, (int) $new->stock);
    }

    public function test_removing_variant_with_stock_is_blocked_and_rolled_back(): void
    {
        $this->giveStock($this->variant, 5);

        $this->saveForm([['sku' => 'VS-1-L', 'price' => 100, 'stock' => 0, 'size' => 'L']])
            ->assertSessionHasErrors('variants');

        $this->assertNotNull(ProductVariant::find($this->variant->id));
        $this->assertNull(ProductVariant::where('sku', 'VS-1-L')->first(), 'transaction geri alınmalı');
    }

    public function test_removing_variant_used_in_production_order_is_blocked(): void
    {
        // Atölye üretim emri (RESTRICT) — Product bu tabloyu bilmeden FK hatasından yakalar.
        $warehouse = Warehouse::factory()->create();
        $orderId = DB::table('production_orders')->insertGetId([
            'code' => 'PO-TEST', 'product_id' => $this->product->id, 'warehouse_id' => $warehouse->id,
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_order_items')->insert([
            'production_order_id' => $orderId, 'product_variant_id' => $this->variant->id,
            'planned_qty' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->saveForm([['sku' => 'VS-1-L', 'price' => 100, 'stock' => 0, 'size' => 'L']])
            ->assertSessionHasErrors('variants');

        $this->assertNotNull(ProductVariant::find($this->variant->id));
    }

    public function test_product_sku_cannot_change_after_creation(): void
    {
        $this->saveForm([['sku' => 'VS-1-M', 'price' => 100, 'stock' => 0, 'size' => 'M']], 'VS-YENI')
            ->assertSessionHasErrors('sku');

        $this->assertSame('VS-1', $this->product->fresh()->sku);
    }
}
