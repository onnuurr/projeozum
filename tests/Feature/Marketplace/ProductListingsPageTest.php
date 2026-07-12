<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductListingsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);
    }

    public function test_index_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/marketplace/products')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Marketplace::ProductListings', false));
    }

    public function test_search_returns_matching_products_by_name_or_sku(): void
    {
        $category = Category::create(['name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);
        Product::create(['category_id' => $category->id, 'name' => 'Mavi Kazak', 'sku' => 'KZK-001', 'gender' => 'Unisex', 'price' => 100]);
        Product::create(['category_id' => $category->id, 'name' => 'Kırmızı Bluz', 'sku' => 'BLZ-002', 'gender' => 'Unisex', 'price' => 80]);

        $this->actingAs($this->admin)
            ->getJson('/marketplace/products/search?q=Kazak')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mavi Kazak');
    }

    public function test_search_with_empty_query_returns_empty_list(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/marketplace/products/search?q=')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
