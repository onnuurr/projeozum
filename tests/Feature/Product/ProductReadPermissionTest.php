<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\database\seeders\ProductPermissionSeeder;
use Modules\Product\Models\Category;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Product okuma ekranlarının yetki kapıları (2026-09-24 yetki revizyonu):
 *   - Ürün listesi → product.view; Kategoriler → category.view; Markalar → brand.view; Depolar → warehouse.view
 *   - Ürün Kartı + pazaryeri listeleme GET → product.card.view (tüm fiyat listeleri +
 *     depo stoklarını gösterir; iş ortağı rolüne verilmez)
 */
class ProductReadPermissionTest extends TestCase
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

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Süveter',
            'sku' => 'SKU-' . uniqid(),
            'gender' => 'Unisex',
            'price' => 107.88,
        ]);
        $product->variants()->create(['sku' => 'V-' . uniqid(), 'price' => 107.88, 'stock' => 5, 'sort_order' => 0]);

        return $product;
    }

    public function test_catalog_lists_require_product_view(): void
    {
        $noPerm = $this->user();
        $viewer = $this->user(['product.view']);

        $this->actingAs($noPerm)->get('/products')->assertForbidden();
        $this->actingAs($viewer)->get('/products')->assertOk();
    }

    public function test_categories_page_requires_category_view(): void
    {
        $this->actingAs($this->user(['product.view']))->get('/products/categories')->assertForbidden();
        $this->actingAs($this->user(['category.view']))->get('/products/categories')->assertOk();
    }

    public function test_warehouses_page_requires_warehouse_view(): void
    {
        $this->actingAs($this->user(['product.view']))->get('/products/warehouses')->assertForbidden();
        $this->actingAs($this->user(['warehouse.view']))->get('/products/warehouses')->assertOk();
    }

    public function test_brands_page_requires_brand_view(): void
    {
        $this->actingAs($this->user(['product.view']))->get('/products/brands')->assertForbidden();
        $this->actingAs($this->user(['brand.view']))->get('/products/brands')->assertOk();
    }

    public function test_product_card_and_listing_require_card_view(): void
    {
        $product = $this->makeProduct();
        Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $viewer = $this->user(['product.view']);
        $this->actingAs($viewer)->get("/products/{$product->id}/card")->assertForbidden();
        $this->actingAs($viewer)->getJson("/products/{$product->id}/marketplaces/n11/listing")->assertForbidden();

        $internal = $this->user(['product.view', 'product.card.view']);
        $this->actingAs($internal)->get("/products/{$product->id}/card")->assertOk();
        $this->actingAs($internal)->getJson("/products/{$product->id}/marketplaces/n11/listing")->assertOk();
    }

    public function test_seeder_strips_management_permissions_from_tenant_role(): void
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $tenant = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'product.delete', 'guard_name' => 'web']);
        $tenant->givePermissionTo('product.delete');

        $this->seed(ProductPermissionSeeder::class);

        $this->assertSame(['product.view'], $tenant->fresh()->permissions->pluck('name')->all());
    }
}
