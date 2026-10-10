<?php

namespace Tests\Feature\Product;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Tests\TestCase;

/**
 * Menşei: products.origin_country sabit alanından Ürün Ayarları → Özellikler'de yönetilen seçimli
 * `mensei` özelliğine taşındı (2026_10_04_120000_move_origin_country_to_mensei_attribute).
 */
class MenseiAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_opens_filterable_select_attribute_with_turkey(): void
    {
        $mensei = ProductAttribute::where('code', 'mensei')->firstOrFail();

        $this->assertSame(ProductAttribute::TYPE_SELECT, $mensei->type);
        $this->assertTrue($mensei->is_filterable);
        $this->assertSame(['Türkiye'], $mensei->options()->pluck('name')->all());
        $this->assertNotContains('mensei', ProductAttribute::RESERVED_CODES);
        $this->assertFalse(Schema::hasColumn('products', 'origin_country'));
    }

    public function test_value_reaches_bagisto_payload_by_name(): void
    {
        $mensei = ProductAttribute::where('code', 'mensei')->firstOrFail();
        $category = Category::create(['name' => 'Kategori', 'slug' => 'kat-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $category->productAttributes()->attach($mensei->id);

        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => 'MNS-1', 'price' => 100,
            'attributes' => ['mensei' => $mensei->options()->value('id')],
        ]);
        $product->variants()->create(['sku' => 'MNS-1-M', 'price' => 100, 'stock' => 1, 'size' => 'M']);

        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');

        $this->assertSame('Türkiye', $payload['attributes']['mensei']);
        $this->assertArrayNotHasKey('origin_country', $payload);
    }

    public function test_migration_round_trips_values(): void
    {
        $migration = require base_path('Modules/Product/database/migrations/2026_10_04_120000_move_origin_country_to_mensei_attribute.php');
        $mensei = ProductAttribute::where('code', 'mensei')->firstOrFail();
        $category = Category::create(['name' => 'Kök', 'slug' => 'kok-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => 'MNS-2', 'price' => 100,
            'attributes' => ['mensei' => $mensei->options()->value('id'), 'kalip' => 5],
        ]);

        $migration->down();
        $this->assertSame('TR', DB::table('products')->where('id', $product->id)->value('origin_country'));
        $this->assertSame(['kalip' => 5], json_decode(DB::table('products')->where('id', $product->id)->value('attributes'), true));
        $this->assertFalse(ProductAttribute::where('code', 'mensei')->exists());

        DB::table('products')->where('id', $product->id)->update(['origin_country' => 'CN']);
        $migration->up();
        $mensei = ProductAttribute::where('code', 'mensei')->firstOrFail();
        $this->assertSame(['Türkiye', 'Çin'], $mensei->options()->pluck('name')->all());
        $this->assertSame($mensei->options()->where('name', 'Çin')->value('id'), $product->fresh()->attributes['mensei']);
        $this->assertSame(5, $product->fresh()->attributes['kalip']);
        // Kök kategorilere atanır (alt kategoriler miras alır)
        $this->assertTrue($category->productAttributes()->whereKey($mensei->id)->exists());
    }
}
