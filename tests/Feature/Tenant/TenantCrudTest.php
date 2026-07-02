<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tenant.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tenant.view', 'guard_name' => 'web']);
        $role->givePermissionTo(['tenant.manage', 'tenant.view']);
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole($role);
    }

    public function test_tenant_can_be_created(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson('/api/v1/tenants', [
                'code' => 'TST01',
                'name' => 'Test Tenant',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'TST01');

        $this->assertDatabaseHas('tenants', ['code' => 'TST01']);
    }

    public function test_tenant_can_be_updated(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/v1/tenants/{$tenant->id}", [
                'name' => 'Güncellenen İsim',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Güncellenen İsim');
    }

    public function test_tenant_suspend_and_activate(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        $this->actingAs($this->superadmin)
            ->patchJson("/api/v1/tenants/{$tenant->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->superadmin)
            ->patchJson("/api/v1/tenants/{$tenant->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_soft_delete_hides_tenant_from_list(): void
    {
        $tenant = Tenant::factory()->create();

        $this->actingAs($this->superadmin)
            ->deleteJson("/api/v1/tenants/{$tenant->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    public function test_non_superadmin_cannot_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/tenants')
            ->assertForbidden();
    }

    public function test_slug_is_immutable_on_name_update(): void
    {
        $tenant = Tenant::factory()->create([
            'slug' => 'ozel-slug',
            'name' => 'Eski Ad',
            'code' => 'REG01',
        ]);

        $this->actingAs($this->superadmin)
            ->putJson("/api/v1/tenants/{$tenant->id}", [
                'name' => 'Yeni Ad',
            ])
            ->assertOk();

        $this->assertDatabaseHas('tenants', [
            'id'   => $tenant->id,
            'slug' => 'ozel-slug',
        ]);
    }
}
