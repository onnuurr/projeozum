<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OperationFasonControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $perm = Permission::firstOrCreate(['name' => 'atelier.manage', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_create_operation(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/operations', [
                'code' => 'overlok', 'name' => 'Overlok',
                'default_location' => 'fason', 'default_unit_cost' => 1.25, 'sort_order' => 25,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('operations', ['code' => 'overlok', 'default_location' => 'fason']);
    }

    public function test_create_fason_supplier(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/fason-suppliers', [
                'name' => 'Yılmaz Dikim', 'phone' => '0555', 'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fason_suppliers', ['name' => 'Yılmaz Dikim']);
    }
}
