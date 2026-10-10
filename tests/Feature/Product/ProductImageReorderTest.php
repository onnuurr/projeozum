<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Product\Events\ProductImagesChanged;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Ürün formu › Resimler: sürükle-bırak sıralaması; ilk görsel kapak olur, tek görsel olayı duyurulur. */
class ProductImageReorderTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('product.add');

        return $user;
    }

    public function test_images_are_reordered_and_first_becomes_cover(): void
    {
        $user = $this->user();
        $product = Product::factory()->create();
        $a = $product->images()->create(['path' => 'p/a.jpg', 'sort_order' => 1, 'is_cover' => true]);
        $b = $product->images()->create(['path' => 'p/b.jpg', 'sort_order' => 2]);
        $c = $product->images()->create(['path' => 'p/c.jpg', 'sort_order' => 3]);
        Event::fake([ProductImagesChanged::class]);

        $this->actingAs($user)->put("/products/{$product->id}/images/order", ['ids' => [$c->id, $a->id]])
            ->assertRedirect()->assertSessionHasNoErrors();

        // Listede olmayan (b) mevcut sırasıyla sona eklenir.
        $this->assertSame([$c->id, $a->id, $b->id], $product->images()->pluck('id')->all());
        $this->assertSame([true, false, false], $product->images()->pluck('is_cover')->all());
        Event::assertDispatchedTimes(ProductImagesChanged::class, 1);
    }

    public function test_images_of_another_product_are_rejected(): void
    {
        $user = $this->user();
        $product = Product::factory()->create();
        $mine = $product->images()->create(['path' => 'p/a.jpg', 'sort_order' => 1]);
        $other = Product::factory()->create()->images()->create(['path' => 'p/x.jpg', 'sort_order' => 1]);

        $this->actingAs($user)->put("/products/{$product->id}/images/order", ['ids' => [$other->id, $mine->id]])
            ->assertSessionHasErrors('ids.0');
        $this->assertSame(1, $other->fresh()->sort_order);
    }

    public function test_requires_product_add_permission(): void
    {
        $product = Product::factory()->create();
        $image = $product->images()->create(['path' => 'p/a.jpg', 'sort_order' => 1]);

        $this->actingAs(User::factory()->create())->put("/products/{$product->id}/images/order", ['ids' => [$image->id]])
            ->assertForbidden();
    }
}
