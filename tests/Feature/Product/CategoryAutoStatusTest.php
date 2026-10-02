<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kategori durumu otomatik: kendisinde ya da alt kategorilerinde yayında ürün yoksa pasif,
 * varsa aktif; elle pasife alınan kategori ürün gelse de pasif kalır (CategoryStatusService).
 */
class CategoryAutoStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Bagisto kategori senkron job'ları
    }

    private function category(string $name, ?Category $parent = null, bool $manuallyPassive = false): Category
    {
        return Category::create([
            'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'parent_id' => $parent?->id,
            'status' => $manuallyPassive ? 'passive' : 'active', 'manually_passive' => $manuallyPassive, 'sort_order' => 0,
        ])->fresh();
    }

    private function product(Category $category, string $status): Product
    {
        return Product::factory()->create(['category_id' => $category->id, 'status' => $status]);
    }

    public function test_new_category_without_products_becomes_passive(): void
    {
        $this->assertSame('passive', $this->category('Kız Çocuk')->status);
    }

    public function test_publishing_a_product_activates_its_category_and_ancestors(): void
    {
        $root = $this->category('Kız Çocuk');
        $child = $this->category('Dış Giyim', $root);
        $sibling = $this->category('Alt Giyim', $root);

        $product = $this->product($child, Product::STATUS_DRAFT);
        $this->assertSame('passive', $child->fresh()->status, 'taslak ürün sayılmaz');

        $product->update(['status' => Product::STATUS_PUBLISHED]);

        $this->assertSame('active', $child->fresh()->status);
        $this->assertSame('active', $root->fresh()->status, 'üst kategori alt kategorinin ürününü sayar');
        $this->assertSame('passive', $sibling->fresh()->status);
    }

    public function test_category_turns_passive_when_last_published_product_leaves(): void
    {
        $root = $this->category('Kız Çocuk');
        $child = $this->category('Dış Giyim', $root);
        $product = $this->product($child, Product::STATUS_PUBLISHED);
        $this->assertSame('active', $root->fresh()->status);

        $product->update(['status' => Product::STATUS_ARCHIVED]);
        $this->assertSame('passive', $child->fresh()->status);
        $this->assertSame('passive', $root->fresh()->status);

        $product->update(['status' => Product::STATUS_PUBLISHED]);
        $this->assertSame('active', $child->fresh()->status);

        $product->delete();
        $this->assertSame('passive', $child->fresh()->status);
    }

    public function test_moving_a_product_to_another_category_updates_both(): void
    {
        $a = $this->category('A');
        $b = $this->category('B');
        $product = $this->product($a, Product::STATUS_PUBLISHED);

        $product->update(['category_id' => $b->id]);

        $this->assertSame('passive', $a->fresh()->status);
        $this->assertSame('active', $b->fresh()->status);
    }

    public function test_manually_passive_category_stays_passive_with_products(): void
    {
        $category = $this->category('Sezon Dışı', null, manuallyPassive: true);
        $this->product($category, Product::STATUS_PUBLISHED);

        $this->assertSame('passive', $category->fresh()->status);
    }

    public function test_form_status_selection_sets_manual_flag(): void
    {
        $category = $this->category('Gömlek');
        $this->product($category, Product::STATUS_PUBLISHED);
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'category.edit', 'guard_name' => 'web']);
        $user->givePermissionTo('category.edit');
        $payload = ['name' => 'Gömlek', 'slug' => $category->slug, 'icon' => 'shirt', 'parent_id' => null, 'sort_order' => 0];

        $this->actingAs($user)->put(route('products.categories.update', $category), $payload + ['status' => 'passive']);
        $this->assertSame('passive', $category->fresh()->status);
        $this->assertTrue($category->fresh()->manually_passive);

        $this->actingAs($user)->put(route('products.categories.update', $category), $payload + ['status' => 'active']);
        $this->assertSame('active', $category->fresh()->status);
        $this->assertFalse($category->fresh()->manually_passive);
    }

    public function test_sync_command_repairs_drift(): void
    {
        $category = $this->category('A');
        $this->product($category, Product::STATUS_PUBLISHED);
        Category::whereKey($category->id)->update(['status' => 'passive']); // olayları atlayan değişiklik

        $this->artisan('categories:sync-status')->assertSuccessful();

        $this->assertSame('active', $category->fresh()->status);
    }
}
