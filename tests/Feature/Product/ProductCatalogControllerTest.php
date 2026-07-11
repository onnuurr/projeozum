<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * ProductController'ın ProductService + ProductCatalogPresenter'a ayrıştırılmasını
 * ve ürün detay payload'unun zenginleştirilmesini (açıklama/özellikler/teknik özellik)
 * uçtan uca doğrular.
 */
class ProductCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
    }

    public function test_store_creates_product_and_derives_price_from_cheapest_variant(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['product.add']))
            ->post(route('products.store'), [
                'name'        => 'Basic Tişört',
                'sku'         => 'TS-001',
                'category_id' => $category->id,
                'gender'      => 'Unisex',
                'variants'    => [
                    ['sku' => 'TS-001-S', 'price' => 250, 'stock' => 5, 'size' => 'S'],
                    ['sku' => 'TS-001-M', 'price' => 180, 'stock' => 3, 'size' => 'M', 'old_price' => 300],
                ],
            ])
            ->assertRedirect(route('products.index'));

        $product = Product::where('sku', 'TS-001')->firstOrFail();

        // Fiyat en ucuz varyanttan türetilir; eski fiyat en düşük dolu old_price.
        $this->assertSame('180.00', $product->price);
        $this->assertSame('300.00', $product->old_price);
        $this->assertSame(2, $product->variants()->count());
        $this->assertNotEmpty($product->slug); // model otomatik slug üretir
    }

    public function test_store_requires_at_least_one_variant(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['product.add']))
            ->post(route('products.store'), [
                'name' => 'Varyantsız', 'sku' => 'NV-1',
                'category_id' => $category->id, 'gender' => 'Unisex',
                'variants' => [],
            ])
            ->assertSessionHasErrors('variants');
    }

    public function test_store_forbidden_without_permission(): void
    {
        $category = $this->category();

        $this->actingAs($this->user())
            ->post(route('products.store'), [
                'name' => 'X', 'sku' => 'X-1',
                'category_id' => $category->id, 'gender' => 'Unisex',
                'variants' => [['sku' => 'X-1-S', 'price' => 10, 'stock' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_detail_page_exposes_rich_description_and_specs(): void
    {
        $category = $this->category();
        $brand    = Brand::create(['name' => 'Marka', 'slug' => 'marka-' . uniqid(), 'sort_order' => 0]);

        $product = Product::create([
            'category_id'        => $category->id,
            'brand_id'           => $brand->id,
            'name'               => 'Detay Ürün',
            'sku'                => 'DET-1',
            'gender'             => 'Kadın',
            'price'              => 199,
            'material'           => 'Pamuk',
            'origin_country'     => 'TR',
            'barcode'            => '8690000000001',
            'care_instructions'  => '30°C yıkayın',
            'free_shipping'      => true,
            'is_domestic'        => true,
            'is_new'             => true,
            'tenant_description' => 'Detaylı B2B açıklaması.',
        ]);
        $product->variants()->create(['sku' => 'DET-1-M', 'price' => 199, 'stock' => 4, 'size' => 'M']);

        $this->actingAs($this->user(['product.view']))
            ->withoutVite()
            ->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Product::ProductDetail', false)
                ->where('product.description', 'Detaylı B2B açıklaması.')
                ->where('product.specs.Materyal', 'Pamuk')
                ->where('product.specs.Menşei', 'TR')
                ->where('product.specs.Barkod', '8690000000001')
                ->where('product.specs.Bakım', '30°C yıkayın')
                ->where('product.features', fn ($features) => collect($features)->contains('Ücretsiz kargo')
                    && collect($features)->contains('Yerli üretim')
                    && collect($features)->contains('Pamuk'))
            );
    }
}
