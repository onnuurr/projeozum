<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Portal ürün detayının zengin içerik + gerçek görsellerle beslendiğini doğrular
 * (placeholder picsum yerine). "Tüm detayları düzelt" kapsamı.
 */
class PortalCatalogDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['portal.access', 'portal.catalog.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.catalog.view']);
    }

    public function test_detail_exposes_rich_content_and_real_images(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'det-' . uniqid()]);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $category = Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
        $product = Product::create([
            'category_id'        => $category->id,
            'name'               => 'Detay Ürün',
            'sku'                => 'DET-' . uniqid(),
            'gender'             => 'Kadın',
            'price'              => 199,
            'material'           => 'Pamuk',
            'origin_country'     => 'TR',
            'barcode'            => '8690000000009',
            'free_shipping'      => true,
            'is_domestic'        => true,
            'tenant_description' => 'Bayiye özel detaylı açıklama.',
        ]);
        $product->variants()->create(['sku' => 'DET-M', 'price' => 199, 'stock' => 5, 'size' => 'M']);
        $product->images()->create(['path' => 'products/' . $product->id . '/kapak.jpg', 'is_cover' => true, 'sort_order' => 0]);

        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$tenant->slug}.bizimsite.test/catalog/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Tenant::Portal/CatalogProduct')
                ->where('product.description', 'Bayiye özel detaylı açıklama.')
                ->where('product.specs.Materyal', 'Pamuk')
                ->where('product.specs.Menşei', 'TR')
                ->where('product.specs.Barkod', '8690000000009')
                ->where('product.features', fn ($f) => collect($f)->contains('Ücretsiz kargo')
                    && collect($f)->contains('Yerli üretim'))
                ->has('product.images', 1)
                ->where('product.images.0', fn ($url) => is_string($url) && ! str_contains($url, 'picsum'))
            );
    }
}
