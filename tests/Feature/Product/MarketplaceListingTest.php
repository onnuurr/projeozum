<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\Marketplace;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceListingTest extends TestCase
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

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Hırka', 'slug' => 'hirka-' . uniqid(), 'status' => 'active', 'sort_order' => 0,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Süveter',
            'sku' => 'SKU-' . uniqid(),
            'gender' => 'Unisex',
            'price' => 107.88,
        ]);

        $product->variants()->create([
            'sku' => 'V-' . uniqid(), 'price' => 107.88, 'stock' => 5, 'sort_order' => 0,
        ]);

        return $product;
    }

    public function test_show_returns_default_draft_when_no_listing_exists(): void
    {
        $product = $this->makeProduct();
        Marketplace::create(['key' => 'n11', 'name' => 'N11', 'logo_text' => 'n11', 'color' => '#f5a623']);

        $this->actingAs($this->admin)
            ->getJson("/products/{$product->id}/marketplaces/n11/listing")
            ->assertOk()
            ->assertJsonPath('listing.is_sent', false)
            ->assertJsonPath('listing.approval_status', 'not_sent')
            ->assertJsonPath('listing.title', 'Test Süveter')
            ->assertJsonPath('marketplace.key', 'n11')
            ->assertJsonCount(1, 'variants');
    }

    public function test_show_returns_404_for_unknown_marketplace(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->admin)
            ->getJson("/products/{$product->id}/marketplaces/bilinmeyen/listing")
            ->assertNotFound();
    }
}
