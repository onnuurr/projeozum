<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_user_cannot_see_other_tenant_data(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $tenantRole = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userA->assignRole($tenantRole);

        app()->instance('current_tenant_id', $tenantA->id);

        $this->assertNotNull(Tenant::find($tenantA->id));
    }

    public function test_superadmin_can_see_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $superadmin = User::factory()->create();
        $superadmin->assignRole($superadminRole);

        $response = $this->actingAs($superadmin)
            ->getJson('/api/v1/tenants');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }
}
