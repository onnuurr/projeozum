<?php
// tests/Feature/Tenant/Portal/PortalUserGateTest.php
namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalUserGateTest extends TestCase
{
    use RefreshDatabase;

    private function bootRbac(): void
    {
        foreach (['portal.access', 'portal.users.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.users.manage']);
        Role::firstOrCreate(['name' => 'tenant-user', 'guard_name' => 'web'])
            ->givePermissionTo('portal.access');
    }

    public function test_admin_can_manage_own_sub_user_but_not_other_admin_or_cross_tenant(): void
    {
        $this->bootRbac();
        $tenantA = Tenant::factory()->create(['slug' => 'ga-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'gb-' . uniqid()]);

        $admin = User::factory()->create(['tenant_id' => $tenantA->id]);
        $admin->assignRole('tenant');

        $subUser = User::factory()->create(['tenant_id' => $tenantA->id]);
        $subUser->assignRole('tenant-user');

        $otherAdmin = User::factory()->create(['tenant_id' => $tenantA->id]);
        $otherAdmin->assignRole('tenant');

        $crossSub = User::factory()->create(['tenant_id' => $tenantB->id]);
        $crossSub->assignRole('tenant-user');

        $this->assertTrue(Gate::forUser($admin)->allows('portal-user.manage', $subUser));
        $this->assertFalse(Gate::forUser($admin)->allows('portal-user.manage', $otherAdmin));
        $this->assertFalse(Gate::forUser($admin)->allows('portal-user.manage', $crossSub));
    }
}
