<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Bagisto\Jobs\PushAttributeOptionToBagisto;
use Modules\Bagisto\Jobs\PushProductAttributeToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Services\ProductAttributeAdminService;
use Modules\Product\Services\VatRateService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Ürün formu Fiyat & Stok sekmesi: ürün bazlı KDV (`kdv_orani` sistem özelliği, kategori oranını
 * ezer) ve maksimum satış adedi (products.max_order_qty → Bagisto maks_satis_adedi).
 */
class ProductPriceTabTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    private function category(?float $vat = null): Category
    {
        return Category::create([
            'name' => 'K '.uniqid(), 'slug' => 'k-'.uniqid(), 'status' => 'active', 'sort_order' => 0, 'vat_rate' => $vat,
        ]);
    }

    private function vatOptionId(string $name): int
    {
        return ProductAttribute::where('code', ProductAttribute::VAT_CODE)->firstOrFail()
            ->options()->where('name', $name)->value('id');
    }

    public function test_migration_creates_vat_system_attribute_with_rate_options(): void
    {
        $options = app(VatRateService::class)->options();

        $this->assertSame(['%0', '%1', '%10', '%20'], array_column($options, 'label'));
        $this->assertSame([0.0, 1.0, 10.0, 20.0], array_column($options, 'rate'));
    }

    public function test_product_vat_option_overrides_category_rate(): void
    {
        $category = $this->category(20);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $vat = app(VatRateService::class);

        $this->assertSame(20.0, $vat->rateFor($product));

        $product->update(['attributes' => [ProductAttribute::VAT_CODE => $this->vatOptionId('%1')]]);
        $this->assertSame(1.0, $vat->rateFor($product->fresh()));
    }

    public function test_store_saves_vat_and_max_qty_without_category_assignment(): void
    {
        $category = $this->category(20);

        $this->actingAs($this->user(['product.add']))
            ->post(route('products.store'), [
                'name' => 'Basic', 'sku' => 'PT-1', 'category_id' => $category->id,
                'max_order_qty' => 3,
                'attributes' => [ProductAttribute::VAT_CODE => $this->vatOptionId('%10')],
                'variants' => [['sku' => 'PT-1-S', 'price' => 100, 'old_price' => 150, 'stock' => 1, 'size' => 'S']],
            ])
            ->assertSessionHasNoErrors();

        $product = Product::where('sku', 'PT-1')->firstOrFail();
        $this->assertSame(3, $product->max_order_qty);
        $this->assertSame($this->vatOptionId('%10'), $product->attributes[ProductAttribute::VAT_CODE]);

        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'created');
        $this->assertSame('kdv-10', $payload['tax_category_code']);
        $this->assertSame(['price' => 165.0, 'special_price' => 110.0], array_intersect_key($payload['variants'][0], ['price' => 1, 'special_price' => 1]));
        $this->assertSame(3, $payload['max_order_qty']);
        // KDV mağazada attribute değil
        $this->assertArrayNotHasKey(ProductAttribute::VAT_CODE, $payload['attributes']);
    }

    public function test_max_order_qty_must_be_positive_integer(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['product.add']))
            ->post(route('products.store'), [
                'name' => 'X', 'sku' => 'PT-2', 'category_id' => $category->id, 'max_order_qty' => 0,
                'variants' => [['sku' => 'PT-2-S', 'price' => 10, 'stock' => 1]],
            ])
            ->assertSessionHasErrors('max_order_qty');
    }

    public function test_form_receives_vat_options_and_hides_system_attribute_from_category_attributes(): void
    {
        $category = $this->category(20);

        $this->actingAs($this->user(['product.add']))
            ->get(route('products.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('vatOptions', 4)
                ->where("categoryVatRates.{$category->id}", 20)
                ->missing("attributeDefinitions.{$category->id}"));
    }

    public function test_vat_attribute_cannot_be_deleted_or_get_non_numeric_options(): void
    {
        $attribute = ProductAttribute::where('code', ProductAttribute::VAT_CODE)->firstOrFail();
        $admin = app(ProductAttributeAdminService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $admin->delete($attribute);
        } finally {
            $this->assertNotNull($attribute->fresh());
        }
    }

    public function test_vat_option_names_must_be_rates(): void
    {
        $attribute = ProductAttribute::where('code', ProductAttribute::VAT_CODE)->firstOrFail();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(ProductAttributeAdminService::class)->update($attribute, [
            'name' => $attribute->name,
            'options' => [...$attribute->options->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->all(), ['name' => '%8']],
        ]);
    }

    public function test_vat_attribute_changes_are_not_pushed_to_bagisto(): void
    {
        Queue::fake();
        $attribute = ProductAttribute::where('code', ProductAttribute::VAT_CODE)->firstOrFail();

        app(ProductAttributeAdminService::class)->update($attribute, [
            'name' => 'KDV',
            'options' => $attribute->options->reverse()->map(fn ($o) => ['id' => $o->id, 'name' => $o->name])->values()->all(),
        ]);

        Queue::assertNotPushed(PushProductAttributeToBagisto::class);
        Queue::assertNotPushed(PushAttributeOptionToBagisto::class);
    }

    public function test_renaming_vat_option_repushes_products_using_it(): void
    {
        $category = $this->category();
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => Product::STATUS_PUBLISHED, 'attributes' => [ProductAttribute::VAT_CODE => $this->vatOptionId('%1')]]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 100]);
        Queue::fake();

        $attribute = ProductAttribute::where('code', ProductAttribute::VAT_CODE)->firstOrFail();
        app(ProductAttributeAdminService::class)->update($attribute, [
            'name' => $attribute->name,
            // %20 seçeneği (kullanılmıyor) kaldırılıp %1 → %20 yapılır
            'options' => $attribute->options->where('name', '!=', '%20')->map(fn ($o) => ['id' => $o->id, 'name' => $o->name === '%1' ? '%20' : $o->name])->values()->all(),
        ]);

        Queue::assertPushed(\Modules\Bagisto\Jobs\PushProductToBagisto::class, fn ($job) => (fn () => $this->productId)->call($job) === $product->id);
        $this->assertSame(20.0, app(VatRateService::class)->rateFor($product->fresh()));
    }
}
