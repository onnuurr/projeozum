<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MaterialControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Granüler izin doğrudan (superadmin değil) — yeni can:atelier.material.manage kapısını sınar.
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'atelier.material.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('atelier.view', 'atelier.material.manage');
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

    public function test_store_with_initial_quantity_records_purchase_movement(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/materials', [
                'code' => 'KUM-010', 'name' => 'Keten', 'type' => 'kumas',
                'unit' => 'metre', 'unit_cost' => 40, 'initial_quantity' => 120.5,
            ])
            ->assertRedirect();

        $material = Material::where('code', 'KUM-010')->firstOrFail();
        $this->assertSame('120.500', $material->current_stock);
        $this->assertDatabaseHas('material_movements', [
            'material_id' => $material->id, 'type' => 'in', 'reason' => 'purchase',
        ]);
    }

    public function test_store_without_initial_quantity_leaves_stock_empty(): void
    {
        $this->actingAs($this->admin)
            ->post('/atelier/materials', [
                'code' => 'KUM-011', 'name' => 'Saten', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 30,
            ])
            ->assertRedirect();

        $material = Material::where('code', 'KUM-011')->firstOrFail();
        $this->assertEquals(0, (float) $material->current_stock);
        $this->assertDatabaseMissing('material_movements', ['material_id' => $material->id]);
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
                'quantity' => 50, 'reason' => 'purchase', 'unit_cost' => 22.5,
            ])
            ->assertRedirect();

        $this->assertSame('50.000', $material->fresh()->current_stock);
        $this->assertDatabaseHas('material_movements', ['material_id' => $material->id, 'unit_cost' => 22.5]);
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

    public function test_view_permission_allows_read_but_not_write(): void
    {
        Permission::firstOrCreate(['name' => 'atelier.view', 'guard_name' => 'web']);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('atelier.view');

        // atelier.view ile listeyi görebilir
        $this->actingAs($viewer)->get('/atelier/materials')->assertOk();

        // ama atelier.material.manage olmadan yazamaz
        $this->actingAs($viewer)
            ->post('/atelier/materials', [
                'code' => 'Y', 'name' => 'Y', 'type' => 'kumas', 'unit' => 'adet', 'unit_cost' => 1,
            ])
            ->assertForbidden();
    }
}
