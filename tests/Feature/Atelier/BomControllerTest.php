<?php

namespace Tests\Feature\Atelier;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Atelier\Models\Material;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BomControllerTest extends TestCase
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

    public function test_save_creates_bom_with_lines(): void
    {
        $cat = Category::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        $product = Product::create(['category_id' => $cat->id, 'name' => 'P', 'sku' => 'S-' . uniqid(), 'gender' => 'Unisex', 'price' => 10]);
        $material = Material::create(['code' => 'K', 'name' => 'Kumaş', 'type' => 'kumas', 'unit' => 'metre', 'unit_cost' => 10]);

        $this->actingAs($this->admin)
            ->post('/atelier/boms', [
                'product_id' => $product->id,
                'name' => 'Std',
                'lines' => [
                    ['material_id' => $material->id, 'quantity_per_unit' => 1.5, 'waste_pct' => 5],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_boms', ['product_id' => $product->id, 'name' => 'Std', 'is_active' => true]);
        $this->assertDatabaseHas('bom_lines', ['material_id' => $material->id, 'quantity_per_unit' => 1.5]);
    }
}
