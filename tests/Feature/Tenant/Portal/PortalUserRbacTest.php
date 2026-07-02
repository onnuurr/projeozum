<?php
// tests/Feature/Tenant/Portal/PortalUserRbacTest.php
namespace Tests\Feature\Tenant\Portal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalUserRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_users_manage_and_tenant_user_role(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $tenant = Role::where('name', 'tenant')->where('guard_name', 'web')->first();
        $this->assertNotNull($tenant);
        $this->assertTrue($tenant->hasPermissionTo('portal.users.manage'));

        $tenantUser = Role::where('name', 'tenant-user')->where('guard_name', 'web')->first();
        $this->assertNotNull($tenantUser);
        $this->assertTrue($tenantUser->hasPermissionTo('portal.access'));
        $this->assertFalse($tenantUser->hasPermissionTo('portal.users.manage'));
        $this->assertFalse($tenantUser->hasPermissionTo('portal.checkout'));
    }
}
