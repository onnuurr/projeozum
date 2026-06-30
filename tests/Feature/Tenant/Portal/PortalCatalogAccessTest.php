<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAccessRule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalCatalogAccessTest extends TestCase
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

    private function createBrand(string $name): int
    {
        return DB::table('brands')->insertGetId([
            'name'       => $name,
            'slug'       => \Illuminate\Support\Str::slug($name),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createProduct(int $brandId, string $name): int
    {
        return DB::table('products')->insertGetId([
            'brand_id'   => $brandId,
            'name'       => $name,
            'slug'       => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'sku'        => 'SKU-' . strtoupper(uniqid()),
            'price'      => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_blocked_brand_product_hidden_from_catalog(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'cat-' . uniqid()]);
        $brandA = $this->createBrand('AlfaBlok');
        $brandB = $this->createBrand('BetaAcik');
        $this->createProduct($brandA, 'Bloklu Ürün');
        $this->createProduct($brandB, 'Açık Ürün');

        // Brand A bloklu kuralı.
        TenantAccessRule::create([
            'tenant_id'  => $tenant->id,
            'scope_type' => TenantAccessRule::SCOPE_BRAND,
            'scope_id'   => $brandA,
            'is_blocked' => true,
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $this->actingAs($user)
            ->get("http://{$tenant->slug}.bizimsite.test/catalog")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Tenant::Portal/Catalog')
                ->where('products.total', 1)
            );
    }
}
