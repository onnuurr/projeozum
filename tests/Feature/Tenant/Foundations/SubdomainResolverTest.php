<?php

namespace Tests\Feature\Tenant\Foundations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubdomainResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Portal permission'ı (TenantPermissionSeeder normalde verir; testte el ile).
        Permission::firstOrCreate(['name' => 'portal.access', 'guard_name' => 'web']);
        $tenantRole = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        $tenantRole->givePermissionTo('portal.access');

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadminRole->givePermissionTo('portal.access');
    }

    private function loginTenantUser(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');
        $this->actingAs($user);

        return $user;
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('http://yok.bizimsite.test/')
            ->assertNotFound();
    }

    public function test_inactive_tenant_returns_403(): void
    {
        $tenant = Tenant::factory()->inactive()->create(['slug' => 'pasif-tenant-' . uniqid()]);
        $this->loginTenantUser($tenant);

        $this->get('http://' . $tenant->slug . '.bizimsite.test/')
            ->assertForbidden();
    }

    public function test_cross_tenant_user_gets_403(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'a-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'b-' . uniqid()]);

        $userOfA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userOfA->assignRole('tenant');

        $this->actingAs($userOfA)
            ->get('http://' . $tenantB->slug . '.bizimsite.test/')
            ->assertForbidden();
    }

    public function test_tenant_user_can_reach_own_portal(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'tt-' . uniqid()]);
        $this->loginTenantUser($tenant);

        $this->get('http://' . $tenant->slug . '.bizimsite.test/')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Tenant::Portal/Dashboard'));
    }

    public function test_superadmin_can_reach_any_tenant_portal(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'su-' . uniqid()]);

        $superadmin = User::factory()->create(['tenant_id' => null]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->get('http://' . $tenant->slug . '.bizimsite.test/')
            ->assertOk();
    }
}
