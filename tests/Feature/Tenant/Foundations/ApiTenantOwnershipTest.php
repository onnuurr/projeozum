<?php

namespace Tests\Feature\Tenant\Foundations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiTenantOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'tenant.manage', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo('tenant.manage');

        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])
            ->givePermissionTo('tenant.manage');
    }

    public function test_tenant_user_cannot_access_other_tenant_endpoints(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userOfA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userOfA->assignRole('tenant');

        $this->actingAs($userOfA)
            ->getJson("/api/v1/tenants/{$tenantB->id}/invoices")
            ->assertForbidden();

        $this->actingAs($userOfA)
            ->getJson("/api/v1/tenants/{$tenantB->id}/settings")
            ->assertForbidden();
    }

    public function test_tenant_user_can_access_own_tenant_endpoints(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $this->actingAs($user)
            ->getJson("/api/v1/tenants/{$tenant->id}/invoices")
            ->assertOk();
    }

    public function test_superadmin_can_access_any_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $superadmin = User::factory()->create(['tenant_id' => null]);
        $superadmin->assignRole('superadmin');

        $this->actingAs($superadmin)
            ->getJson("/api/v1/tenants/{$tenant->id}/invoices")
            ->assertOk();
    }
}
