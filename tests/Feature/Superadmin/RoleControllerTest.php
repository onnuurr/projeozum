<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    public function test_tenant_role_cannot_be_renamed(): void
    {
        $admin = $this->actingAsSuperadmin();
        $tenantRole = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->put(route('roles.update', $tenantRole), ['name' => 'bayi'])
            ->assertSessionHas('error');

        $this->assertSame('tenant', $tenantRole->fresh()->name);
    }

    public function test_tenant_role_cannot_be_deleted(): void
    {
        $admin = $this->actingAsSuperadmin();
        $tenantRole = Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->delete(route('roles.destroy', $tenantRole))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $tenantRole->id, 'name' => 'tenant']);
    }

    public function test_superadmin_role_still_cannot_be_renamed_or_deleted(): void
    {
        $admin = $this->actingAsSuperadmin();
        $superadminRole = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->put(route('roles.update', $superadminRole), ['name' => 'yonetici'])
            ->assertSessionHas('error');
        $this->actingAs($admin)
            ->delete(route('roles.destroy', $superadminRole))
            ->assertSessionHas('error');

        $this->assertSame('superadmin', $superadminRole->fresh()->name);
    }

    public function test_custom_role_can_still_be_renamed_freely(): void
    {
        $admin = $this->actingAsSuperadmin();
        $custom = Role::firstOrCreate(['name' => 'kalipci', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->put(route('roles.update', $custom), ['name' => 'kalipci-ekip'])
            ->assertSessionHasNoErrors();

        $this->assertSame('kalipci-ekip', $custom->fresh()->name);
    }
}
