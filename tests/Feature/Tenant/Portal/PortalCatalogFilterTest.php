<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Portal katalogunda kategori/marka filtresi, arama ve sıralamayı doğrular
 * ("kolay ürün bulma"). Erişim scope'u PortalCatalogAccessTest'te ayrıca kapsanır.
 */
class PortalCatalogFilterTest extends TestCase
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

    private function brand(string $name): int
    {
        return DB::table('brands')->insertGetId([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function category(string $name): int
    {
        return DB::table('product_categories')->insertGetId([
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function product(int $categoryId, int $brandId, string $name, float $price): int
    {
        return DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => $brandId,
            'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(),
            'sku' => 'SKU-' . strtoupper(uniqid()), 'price' => $price,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function tenantUser(): array
    {
        $tenant = Tenant::factory()->create(['slug' => 'cat-' . uniqid()]);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        return [$tenant, $user];
    }

    public function test_category_filter_limits_results(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $brand = $this->brand('Marka');
        $catA  = $this->category('Elbise');
        $catB  = $this->category('Ayakkabı');
        $this->product($catA, $brand, 'Yazlık Elbise', 100);
        $this->product($catB, $brand, 'Spor Ayakkabı', 200);

        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$tenant->slug}.bizimsite.test/catalog?category={$catA}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Tenant::Portal/Catalog')
                ->where('products.total', 1)
                ->where('filters.category', $catA)
                ->has('filterOptions.categories', 2)
                ->has('filterOptions.brands', 1)
            );
    }

    public function test_brand_filter_and_search_combine(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $cat    = $this->category('Elbise');
        $brandA = $this->brand('Alfa');
        $brandB = $this->brand('Beta');
        $this->product($cat, $brandA, 'Alfa Elbise', 100);
        $this->product($cat, $brandB, 'Beta Elbise', 200);

        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$tenant->slug}.bizimsite.test/catalog?brand={$brandB}&q=Elbise")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Beta Elbise')
            );
    }

    public function test_price_sort_orders_ascending(): void
    {
        [$tenant, $user] = $this->tenantUser();
        $cat   = $this->category('Elbise');
        $brand = $this->brand('Marka');
        $this->product($cat, $brand, 'Pahalı', 500);
        $this->product($cat, $brand, 'Ucuz', 50);

        $this->actingAs($user)
            ->withoutVite()
            ->get("http://{$tenant->slug}.bizimsite.test/catalog?sort=price_asc")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('products.data.0.name', 'Ucuz')
                ->where('products.data.1.name', 'Pahalı')
            );
    }
}
