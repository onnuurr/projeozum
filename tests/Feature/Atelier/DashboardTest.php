<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_admin(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->get('/atelier')
            ->assertOk();
    }

    public function test_dashboard_forbidden_without_permission(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)
            ->get('/atelier')
            ->assertForbidden();
    }
}
