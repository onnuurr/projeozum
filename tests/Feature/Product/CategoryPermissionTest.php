<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Kategori ekleme (category.create) ve düzenleme (category.edit) yetkileri
 * category.manage'den (silme / özellik / pazaryeri eşleme) ayrıdır.
 */
class CategoryPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = []): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Tişört', 'slug' => 'tisort', 'icon' => 'shirt',
            'parent_id' => null, 'status' => 'active', 'sort_order' => 0,
        ], $overrides);
    }

    private function category(): Category
    {
        return Category::create([
            'name' => 'Gömlek', 'slug' => 'gomlek', 'icon' => 'package',
            'status' => 'active', 'sort_order' => 0,
        ]);
    }

    public function test_store_requires_category_create(): void
    {
        $this->actingAs($this->user(['category.manage']))
            ->post(route('products.categories.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_store_succeeds_with_category_create(): void
    {
        $this->actingAs($this->user(['category.create']))
            ->post(route('products.categories.store'), $this->payload())
            ->assertRedirect(route('products.categories.index'));

        $this->assertDatabaseHas('product_categories', ['slug' => 'tisort', 'icon' => 'shirt']);
    }

    public function test_store_rejects_emoji_icon(): void
    {
        $this->actingAs($this->user(['category.create']))
            ->post(route('products.categories.store'), $this->payload(['icon' => '👕']))
            ->assertSessionHasErrors('icon');
    }

    public function test_store_accepts_prefixed_icon_names(): void
    {
        $user = $this->user(['category.create']);

        foreach (['lab:shirt-long-sleeve' => 'uzun-kollu', 'oz:cocuk-etek' => 'cocuk-etek'] as $icon => $slug) {
            $this->actingAs($user)
                ->post(route('products.categories.store'), $this->payload(['icon' => $icon, 'slug' => $slug]))
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('product_categories', ['slug' => $slug, 'icon' => $icon]);
        }
    }

    public function test_store_rejects_unknown_icon_prefix(): void
    {
        $this->actingAs($this->user(['category.create']))
            ->post(route('products.categories.store'), $this->payload(['icon' => 'evil:shirt']))
            ->assertSessionHasErrors('icon');
    }

    public function test_update_requires_category_edit(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['category.manage']))
            ->put(route('products.categories.update', $category), $this->payload(['slug' => 'gomlek']))
            ->assertForbidden();
    }

    public function test_update_succeeds_with_category_edit(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['category.edit']))
            ->put(route('products.categories.update', $category), $this->payload(['name' => 'Gömlek Yeni', 'slug' => 'gomlek']))
            ->assertRedirect();

        $this->assertDatabaseHas('product_categories', ['id' => $category->id, 'name' => 'Gömlek Yeni']);
    }

    public function test_update_rejects_moving_category_under_its_own_descendant(): void
    {
        $parent = $this->category();
        $child = Category::create(['name' => 'Dış Giyim', 'slug' => 'dis-giyim', 'parent_id' => $parent->id, 'status' => 'active', 'sort_order' => 0]);
        $grandchild = Category::create(['name' => 'Mont', 'slug' => 'mont', 'parent_id' => $child->id, 'status' => 'active', 'sort_order' => 0]);
        $user = $this->user(['category.edit']);

        foreach ([$parent->id, $grandchild->id] as $newParent) {
            $this->actingAs($user)
                ->put(route('products.categories.update', $parent), $this->payload(['slug' => 'gomlek', 'parent_id' => $newParent]))
                ->assertSessionHasErrors('parent_id');
        }

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_update_allows_moving_category_under_another_branch(): void
    {
        $parent = $this->category();
        $other = Category::create(['name' => 'Kız Çocuk', 'slug' => 'kiz-cocuk', 'status' => 'active', 'sort_order' => 0]);

        $this->actingAs($this->user(['category.edit']))
            ->put(route('products.categories.update', $parent), $this->payload(['slug' => 'gomlek', 'parent_id' => $other->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($other->id, $parent->fresh()->parent_id);
    }

    public function test_category_edit_does_not_allow_delete(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['category.edit']))
            ->delete(route('products.categories.destroy', $category))
            ->assertForbidden();
    }
}
