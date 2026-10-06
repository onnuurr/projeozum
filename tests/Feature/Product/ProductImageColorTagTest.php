<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Product;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Ürün formu: yalın fotoğraf bir renge etiketlenir (Creative mokap o rengi bu fotoğraftan giydirir). */
class ProductImageColorTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_can_be_tagged_with_a_color_and_reset_to_all_colors(): void
    {
        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('product.add');
        $image = Product::factory()->create()->images()->create(['path' => 'p/a.jpg', 'sort_order' => 0]);

        $this->actingAs($user)->put("/products/images/{$image->id}", ['variant_colors' => ['Yeşil']])->assertRedirect();
        $this->assertSame(['Yeşil'], $image->fresh()->variant_colors);

        $this->actingAs($user)->put("/products/images/{$image->id}", ['variant_colors' => null])->assertRedirect();
        $this->assertNull($image->fresh()->variant_colors);

        $this->actingAs($user)->put("/products/images/{$image->id}", ['variant_colors' => 'Yeşil'])->assertSessionHasErrors('variant_colors');
    }
}
