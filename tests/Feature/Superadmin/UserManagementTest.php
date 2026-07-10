<?php

namespace Tests\Feature\Superadmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'users.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'users.manage', 'guard_name' => 'web']);
    }

    private function superadmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole($role);
        $admin->givePermissionTo('users.view', 'users.manage');

        return $admin;
    }

    public function test_plain_user_is_forbidden_from_users_index(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)->get('/superadmin/users')->assertForbidden();
    }

    public function test_superadmin_can_list_users(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->get('/superadmin/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Superadmin::Users')
                ->has('users', 1)
                ->has('roles')
                ->has('tenants'));
    }

    public function test_superadmin_can_create_user_with_role(): void
    {
        $admin = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);

        $this->actingAs($admin)->post('/superadmin/users', [
            'name'                  => 'Test Yönetici',
            'email'                 => 'yonetici@example.test',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'yonetim',
        ])->assertRedirect();

        $created = User::where('email', 'yonetici@example.test')->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('yonetim'));
        $this->assertTrue($created->is_active);
    }

    public function test_update_without_password_keeps_existing_password(): void
    {
        $admin  = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);
        $target = User::factory()->create(['password' => 'original-hash-marker']);
        $target->assignRole('yonetim');
        $originalHash = $target->password;

        $this->actingAs($admin)->put("/superadmin/users/{$target->id}", [
            'name'  => 'Güncellenmiş İsim',
            'email' => $target->email,
            'role'  => 'yonetim',
        ])->assertRedirect();

        $target->refresh();
        $this->assertSame('Güncellenmiş İsim', $target->name);
        $this->assertSame($originalHash, $target->password);
    }

    public function test_superadmin_role_cannot_be_changed_via_update(): void
    {
        $admin = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);
        $otherSuperadmin = User::factory()->create();
        $otherSuperadmin->assignRole('superadmin');

        $this->actingAs($admin)->put("/superadmin/users/{$otherSuperadmin->id}", [
            'name'  => $otherSuperadmin->name,
            'email' => $otherSuperadmin->email,
            'role'  => 'yonetim',
        ])->assertRedirect();

        $this->assertTrue($otherSuperadmin->fresh()->hasRole('superadmin'));
    }

    public function test_cannot_delete_self(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)
            ->delete("/superadmin/users/{$admin->id}")
            ->assertRedirect();

        $this->assertNotNull($admin->fresh());
    }

    public function test_can_delete_a_superadmin_when_another_remains(): void
    {
        $admin       = $this->superadmin();
        $secondAdmin = User::factory()->create();
        $secondAdmin->assignRole('superadmin');

        $this->actingAs($admin)
            ->delete("/superadmin/users/{$secondAdmin->id}")
            ->assertRedirect();

        $this->assertTrue($secondAdmin->fresh()->trashed());
    }

    public function test_last_superadmin_guard_blocks_deleting_the_sole_superadmin(): void
    {
        $onlyAdmin = $this->superadmin();

        $this->actingAs($onlyAdmin)
            ->delete("/superadmin/users/{$onlyAdmin->id}")
            ->assertRedirect();

        $this->assertNotNull($onlyAdmin->fresh());
        $this->assertSame(1, User::role('superadmin')->count());
    }

    public function test_toggle_active_flips_status(): void
    {
        $admin  = $this->superadmin();
        Role::firstOrCreate(['name' => 'yonetim', 'guard_name' => 'web']);
        $target = User::factory()->create(['is_active' => true]);
        $target->assignRole('yonetim');

        $this->actingAs($admin)
            ->post("/superadmin/users/{$target->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($target->fresh()->is_active);
    }
}
