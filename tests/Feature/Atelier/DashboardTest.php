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

    public function test_dashboard_renders_with_view_permission(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('atelier.view');

        $this->actingAs($user)
            ->get('/atelier')
            ->assertOk();
    }

    public function test_dashboard_renders_for_superadmin_via_bypass(): void
    {
        // Superadmin, açık izin olmadan Gate::before ile geçer.
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
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
