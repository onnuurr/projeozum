<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaterialControllerTest extends TestCase
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

    public function test_store_creates_material(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/materials', [
                'code' => 'KUM-001', 'name' => 'Pamuk Kumaş',
                'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 12.5,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('materials', ['code' => 'KUM-001', 'name' => 'Pamuk Kumaş']);
    }

    public function test_movement_increases_stock(): void
    {
        $material = Material::create([
            'code' => 'KUM-002', 'name' => 'Kot', 'type' => 'kumas',
            'unit' => 'metre', 'unit_cost' => 20, 'current_stock' => 0,
        ]);

        $this->actingAs($this->admin)
            ->post('/atelier/materials/movement', [
                'material_id' => $material->id, 'type' => 'in',
                'quantity' => 50, 'reason' => 'purchase',
            ])
            ->assertRedirect();

        $this->assertSame('50.000', $material->fresh()->current_stock);
    }

    public function test_requires_permission(): void
    {
        $plain = User::factory()->create();

        $this->actingAs($plain)
            ->post('/atelier/materials', [
                'code' => 'X', 'name' => 'X', 'type' => 'kumas', 'unit' => 'adet', 'unit_cost' => 1,
            ])
            ->assertForbidden();
    }
}
