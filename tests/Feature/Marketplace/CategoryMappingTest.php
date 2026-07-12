<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Database\Seeders\MarketplacePermissionSeeder;
use Modules\Marketplace\Models\CategoryMarketplaceMapping;
use Modules\Marketplace\Models\Marketplace;
use Modules\Product\Models\Category;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
        (new MarketplacePermissionSeeder())->run();
    }

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0,
        ]);
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/marketplace/categories')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketplace::CategoryMapping', false));
    }

    public function test_store_mapping_creates_mapping(): void
    {
        $category = $this->makeCategory();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $this->actingAs($this->admin)
            ->post("/marketplace/categories/{$category->id}/marketplaces/{$mp->id}", [
                'category_path' => 'Giyim > Üst Giyim > Hırka',
            ])
            ->assertRedirect(route('marketplace.categories.index'));

        $this->assertDatabaseHas('category_marketplace_mappings', [
            'category_id' => $category->id,
            'marketplace_id' => $mp->id,
            'category_path' => 'Giyim > Üst Giyim > Hırka',
        ]);
    }

    public function test_destroy_mapping_removes_mapping(): void
    {
        $category = $this->makeCategory();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);
        CategoryMarketplaceMapping::create([
            'category_id' => $category->id, 'marketplace_id' => $mp->id,
            'category_path' => 'X', 'external_id' => 'auto-1',
        ]);

        $this->actingAs($this->admin)
            ->delete("/marketplace/categories/{$category->id}/marketplaces/{$mp->id}")
            ->assertRedirect(route('marketplace.categories.index'));

        $this->assertDatabaseMissing('category_marketplace_mappings', [
            'category_id' => $category->id, 'marketplace_id' => $mp->id,
        ]);
    }

    public function test_store_mapping_requires_catalog_manage_permission(): void
    {
        $category = $this->makeCategory();
        $mp = Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);
        $plainUser = User::factory()->create();

        $this->actingAs($plainUser)
            ->post("/marketplace/categories/{$category->id}/marketplaces/{$mp->id}", [
                'category_path' => 'X',
            ])
            ->assertForbidden();
    }
}
