<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Cinsiyet: products.gender sabit alanından Ürün Ayarları → Özellikler'deki seçimli `cinsiyet`
 * özelliğine taşındı (2026_10_04_150000_move_gender_to_cinsiyet_attribute).
 */
class GenderAttributeTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'Modules/Product/database/migrations/2026_10_04_150000_move_gender_to_cinsiyet_attribute.php';

    private function gender(): ProductAttribute
    {
        return ProductAttribute::where('code', Product::GENDER_ATTRIBUTE)->firstOrFail();
    }

    private function optionId(string $name): int
    {
        return $this->gender()->options()->where('name', $name)->value('id');
    }

    public function test_migration_opens_select_attribute_and_drops_column(): void
    {
        $gender = $this->gender();

        $this->assertSame(ProductAttribute::TYPE_SELECT, $gender->type);
        $this->assertTrue($gender->is_filterable);
        $this->assertSame(['Kız', 'Erkek', 'Unisex'], $gender->options()->orderBy('sort_order')->pluck('name')->all());
        $this->assertFalse(Schema::hasColumn('products', 'gender'));
    }

    public function test_product_gender_is_read_from_attribute_and_reaches_bagisto(): void
    {
        $category = Category::create(['name' => 'Kök', 'slug' => 'kok-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $category->productAttributes()->attach($this->gender()->id, ['required' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => 'GND-1', 'price' => 100,
            'attributes' => [Product::GENDER_ATTRIBUTE => $this->optionId('Erkek')],
        ]);
        $product->variants()->create(['sku' => 'GND-1-M', 'price' => 100, 'stock' => 1, 'size' => 'M']);

        $this->assertSame('Erkek', $product->fresh()->gender);
        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');
        $this->assertSame('Erkek', $payload['attributes'][Product::GENDER_ATTRIBUTE]);

        // Zorunlu: ürün formunda cinsiyetsiz kayıt reddedilir
        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $editor = User::factory()->create();
        $editor->givePermissionTo('product.add');
        $this->actingAs($editor)->put(route('products.update', $product), [
            'name' => 'Ürün', 'sku' => 'GND-1', 'category_id' => $category->id,
            'variants' => [['sku' => 'GND-1-M', 'price' => 100, 'stock' => 1, 'size' => 'M']],
            'attributes' => [],
        ])->assertSessionHasErrors('attributes.'.Product::GENDER_ATTRIBUTE);
    }

    public function test_migration_round_trips_and_reuses_existing_attribute(): void
    {
        $migration = require base_path(self::MIGRATION);
        $root = Category::create(['name' => 'Kız Çocuk', 'slug' => 'kiz-'.uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $girl = Product::create([
            'category_id' => $root->id, 'name' => 'A', 'sku' => 'GND-A', 'price' => 10,
            'attributes' => [Product::GENDER_ATTRIBUTE => $this->optionId('Kız'), 'kalip' => 5],
        ]);

        $migration->down();
        $this->assertSame('Kadın', DB::table('products')->where('id', $girl->id)->value('gender'));
        $this->assertTrue(ProductAttribute::where('code', Product::GENDER_ATTRIBUTE)->exists()); // ayar kaydı silinmez

        // Canlıdaki önceki hal: ayarlardan açılmış "Kadın / Kız" + "Erkek", Unisex yok; özellik değeri olmayan ürün
        $attribute = $this->gender();
        $girlOptionId = $this->optionId('Kız');
        $attribute->options()->where('name', 'Unisex')->delete();
        $attribute->options()->whereKey($girlOptionId)->update(['name' => 'Kadın / Kız']);
        $boy = DB::table('products')->insertGetId([
            'category_id' => $root->id, 'name' => 'B', 'slug' => 'b-'.uniqid(), 'sku' => 'GND-B', 'price' => 10,
            'gender' => 'Erkek', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $girl->id)->update(['attributes' => json_encode(['kalip' => 5]), 'gender' => 'Kadın']);

        $migration->up();

        $this->assertSame(1, ProductAttribute::where('code', Product::GENDER_ATTRIBUTE)->count());
        $this->assertSame($girlOptionId, $this->optionId('Kız')); // yeniden adlandırma id'yi korur
        $this->assertSame(['Kız', 'Erkek', 'Unisex'], $this->gender()->options()->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame('Kız', $girl->fresh()->gender);
        $this->assertSame(5, $girl->fresh()->attributes['kalip']);
        $this->assertSame('Erkek', Product::find($boy)->gender);
        // Kök kategorilere zorunlu atanır
        $this->assertTrue((bool) $root->productAttributes()->whereKey($this->gender()->id)->first()?->pivot->required);
    }
}
