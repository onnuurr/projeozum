<?php

namespace Tests\Feature\Product;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Models\Category;
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

    public function test_color_can_be_given_while_uploading_with_the_product(): void
    {
        Queue::fake();
        Storage::fake(Media::disk());
        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('product.add');
        $category = Category::create(['name' => 'Etek', 'slug' => 'etek-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Renkli', 'sku' => 'RK-1', 'category_id' => $category->id,
            'variants' => [
                ['sku' => 'RK-1-Y', 'price' => 100, 'stock' => 1, 'size' => '7 Yaş', 'color_name' => 'Yeşil'],
                ['sku' => 'RK-1-B', 'price' => 100, 'stock' => 1, 'size' => '7 Yaş', 'color_name' => 'Bej'],
            ],
            'images'       => [UploadedFile::fake()->image('y.jpg'), UploadedFile::fake()->image('g.jpg')],
            'image_colors' => ['Yeşil', ''],
        ])->assertSessionHasNoErrors();

        $images = Product::where('sku', 'RK-1')->firstOrFail()->images()->orderBy('sort_order')->get();
        $this->assertSame(['Yeşil'], $images[0]->variant_colors);
        $this->assertNull($images[1]->variant_colors);   // boş = bütün renkler
    }
}
