<?php

namespace Tests\Unit\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Enums\Capability;
use Modules\Product\Exceptions\ProductNotReadyException;
use Modules\Product\Models\Category;
use Modules\Product\Models\CategoryMarketplaceMapping;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductReadinessService;
use Tests\TestCase;

/**
 * Faz 4: Readiness Engine — her capability için eksik/tam senaryolar.
 * Manken/poz gibi Creative-içi durumlar bilerek dışarıda (bkz. plan Karar 1) —
 * yalnızca Product'ın kendi verisi (görsel/varyant/kategori eşleme) test edilir.
 */
class ProductReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function category(): Category
    {
        return Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
    }

    private function product(Category $category): Product
    {
        return Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => 'RDY-' . uniqid(),
            'gender' => 'Unisex', 'price' => 100,
        ]);
    }

    public function test_try_on_missing_image(): void
    {
        $product = $this->product($this->category());

        $result = app(ProductReadinessService::class)->evaluate($product, Capability::TryOn);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['score']);
        $this->assertContains('En az bir ürün görseli gerekli', $result['missing']);
    }

    public function test_try_on_ready_with_image(): void
    {
        $product = $this->product($this->category());
        $product->images()->create(['path' => 'products/1/a.jpg', 'sort_order' => 1, 'is_cover' => true]);
        $product->load('images');

        $result = app(ProductReadinessService::class)->evaluate($product, Capability::TryOn);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['missing']);
    }

    public function test_marketplace_push_reports_all_missing_reasons(): void
    {
        $product = $this->product($this->category());

        $result = app(ProductReadinessService::class)->evaluate($product, Capability::MarketplacePush);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['score']);
        $this->assertCount(3, $result['missing']);
    }

    public function test_marketplace_push_ready_when_all_conditions_met(): void
    {
        $category = $this->category();
        $product  = $this->product($category);
        $product->images()->create(['path' => 'products/1/a.jpg', 'sort_order' => 1, 'is_cover' => true]);
        $product->variants()->create(['sku' => 'RDY-V1', 'price' => 100, 'stock' => 5]);

        $marketplace = Marketplace::create(['key' => 'trendyol-' . uniqid(), 'name' => 'Trendyol', 'sort_order' => 0]);
        CategoryMarketplaceMapping::create([
            'category_id' => $category->id, 'marketplace_id' => $marketplace->id,
            'category_path' => 'Giyim > Tişört', 'external_id' => '123',
        ]);

        $product->load(['images', 'variants', 'category.marketplaceMappings']);

        $result = app(ProductReadinessService::class)->evaluate($product, Capability::MarketplacePush);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['missing']);
    }

    public function test_marketplace_push_ignores_zero_stock_variant(): void
    {
        $category = $this->category();
        $product  = $this->product($category);
        $product->variants()->create(['sku' => 'RDY-V2', 'price' => 100, 'stock' => 0]);
        $product->load('variants');

        $result = app(ProductReadinessService::class)->evaluate($product, Capability::MarketplacePush);

        $this->assertContains('En az bir varyant (SKU + stok) gerekli', $result['missing']);
    }

    public function test_assert_ready_throws_when_not_ready(): void
    {
        $product = $this->product($this->category());

        $this->expectException(ProductNotReadyException::class);

        app(ProductReadinessService::class)->assertReady($product, Capability::TryOn);
    }

    public function test_assert_ready_passes_silently_when_ready(): void
    {
        $product = $this->product($this->category());
        $product->images()->create(['path' => 'products/1/a.jpg', 'sort_order' => 1, 'is_cover' => true]);
        $product->load('images');

        app(ProductReadinessService::class)->assertReady($product, Capability::CreativeRender);

        $this->assertTrue(true);
    }
}
