<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSuperadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    public function test_store_rejects_name_not_matching_module_dot_action_format(): void
    {
        $admin = $this->actingAsSuperadmin();

        $this->actingAs($admin)
            ->post(route('permissions.store'), ['name' => 'GecersizIsim'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('permissions', ['name' => 'GecersizIsim']);
    }

    public function test_store_accepts_valid_module_dot_action_name(): void
    {
        $admin = $this->actingAsSuperadmin();

        $this->actingAs($admin)
            ->post(route('permissions.store'), ['name' => 'reports.manage'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'reports.manage', 'guard_name' => 'web']);
    }

    public function test_update_ignores_name_change_and_only_updates_display_name(): void
    {
        $admin = $this->actingAsSuperadmin();
        $permission = Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->put(route('permissions.update', $permission), [
                'name' => 'atelier.goruntule',
                'display_name' => 'Atölye Görüntüle (güncel)',
            ])
            ->assertSessionHasNoErrors();

        $permission->refresh();
        $this->assertSame('atelier.view', $permission->name);
        $this->assertSame('Atölye Görüntüle (güncel)', $permission->display_name);
    }
}
