<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Category;
use Modules\Product\Models\CategoryAttributeDefinition;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Faz 3: Attribute Engine — kategori özellik tanımları CRUD'u ve ürün
 * kaydında kategoriye göre dinamik doğrulama + whitelist kesişimi.
 */
class CategoryAttributeDefinitionTest extends TestCase
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

    private function category(): Category
    {
        return Category::create([
            'name' => 'Tişört', 'slug' => 'tisort-' . uniqid(),
            'status' => 'active', 'sort_order' => 0,
        ]);
    }

    public function test_store_definition_requires_permission(): void
    {
        $category = $this->category();

        $this->actingAs($this->user())
            ->post(route('products.categories.attribute-definitions.store', $category), [
                'key' => 'yaka', 'label' => 'Yaka Tipi', 'type' => 'string',
            ])
            ->assertForbidden();
    }

    public function test_store_definition_succeeds_with_permission(): void
    {
        $category = $this->category();

        $this->actingAs($this->user(['category.manage']))
            ->post(route('products.categories.attribute-definitions.store', $category), [
                'key' => 'yaka', 'label' => 'Yaka Tipi', 'type' => 'enum',
                'options' => ['V Yaka', 'Bisiklet Yaka'], 'required' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('category_attribute_definitions', [
            'category_id' => $category->id, 'key' => 'yaka', 'type' => 'enum', 'required' => true,
        ]);
    }

    public function test_store_definition_rejects_duplicate_key_in_same_category(): void
    {
        $category = $this->category();
        CategoryAttributeDefinition::create([
            'category_id' => $category->id, 'key' => 'yaka', 'label' => 'Yaka', 'type' => 'string',
        ]);

        $this->actingAs($this->user(['category.manage']))
            ->post(route('products.categories.attribute-definitions.store', $category), [
                'key' => 'yaka', 'label' => 'Yaka Tekrar', 'type' => 'string',
            ])
            ->assertSessionHasErrors('key');
    }

    public function test_destroy_definition(): void
    {
        $category   = $this->category();
        $definition = CategoryAttributeDefinition::create([
            'category_id' => $category->id, 'key' => 'yaka', 'label' => 'Yaka', 'type' => 'string',
        ]);

        $this->actingAs($this->user(['category.manage']))
            ->delete(route('products.categories.attribute-definitions.destroy', [$category, $definition]))
            ->assertRedirect();

        $this->assertDatabaseMissing('category_attribute_definitions', ['id' => $definition->id]);
    }

    public function test_product_update_validates_required_category_attribute(): void
    {
        $category = $this->category();
        CategoryAttributeDefinition::create([
            'category_id' => $category->id, 'key' => 'yaka', 'label' => 'Yaka Tipi',
            'type' => 'enum', 'options' => ['V Yaka', 'Bisiklet Yaka'], 'required' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => 'ATT-1',
            'gender' => 'Unisex', 'price' => 100,
        ]);
        $product->variants()->create(['sku' => 'ATT-1-M', 'price' => 100, 'stock' => 1, 'size' => 'M']);

        $this->actingAs($this->user(['product.add']))
            ->put(route('products.update', $product), [
                'name' => 'Ürün', 'sku' => 'ATT-1', 'category_id' => $category->id, 'gender' => 'Unisex',
                'variants' => [['sku' => 'ATT-1-M', 'price' => 100, 'stock' => 1, 'size' => 'M']],
                'attributes' => [],
            ])
            ->assertSessionHasErrors('attributes.yaka');
    }

    public function test_product_update_saves_valid_attributes_and_drops_undefined_keys(): void
    {
        $category = $this->category();
        CategoryAttributeDefinition::create([
            'category_id' => $category->id, 'key' => 'yaka', 'label' => 'Yaka Tipi',
            'type' => 'enum', 'options' => ['V Yaka', 'Bisiklet Yaka'], 'required' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Ürün', 'sku' => 'ATT-2',
            'gender' => 'Unisex', 'price' => 100,
        ]);
        $product->variants()->create(['sku' => 'ATT-2-M', 'price' => 100, 'stock' => 1, 'size' => 'M']);

        $this->actingAs($this->user(['product.add']))
            ->put(route('products.update', $product), [
                'name' => 'Ürün', 'sku' => 'ATT-2', 'category_id' => $category->id, 'gender' => 'Unisex',
                'variants' => [['sku' => 'ATT-2-M', 'price' => 100, 'stock' => 1, 'size' => 'M']],
                'attributes' => ['yaka' => 'V Yaka', 'tanimsiz_anahtar' => 'silinmeli'],
            ])
            ->assertRedirect();

        $product->refresh();
        $this->assertSame(['yaka' => 'V Yaka'], $product->attributes);
    }
}
