<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Database\Seeders\MarketplacePermissionSeeder;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceCatalogPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_role_gets_catalog_manage_permission(): void
    {
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        (new MarketplacePermissionSeeder())->run();

        $superadmin = Role::where('name', 'superadmin')->where('guard_name', 'web')->first();
        $tenant     = Role::where('name', 'tenant')->where('guard_name', 'web')->first();

        $this->assertTrue($superadmin->hasPermissionTo('marketplace.catalog.manage'));
        $this->assertFalse($tenant->hasPermissionTo('marketplace.catalog.manage'));
        $this->assertTrue($tenant->hasPermissionTo('marketplace.manage'));
    }
}
