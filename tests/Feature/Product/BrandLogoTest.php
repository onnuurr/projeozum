<?php

namespace Tests\Feature\Product;

use App\Models\User;
use App\Support\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Models\Brand;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Marka görseli — ikon (ortak ikon kaydı) ve logo: yükleme, değiştirme (eski dosya silinir), kaldırma, marka silinince dosya da gider. */
class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create();
        foreach (['brand.view', 'brand.manage'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $user->givePermissionTo($p);
        }

        return $user;
    }

    public function test_logo_upload_replace_remove_and_delete(): void
    {
        Storage::fake(Media::disk());
        $user = $this->manager();

        $this->actingAs($user)->post('/products/brands', [
            'name' => 'Nike', 'slug' => 'nike', 'sort_order' => 1,
            'logo' => UploadedFile::fake()->image('nike.png', 200, 200),
        ])->assertRedirect();

        $brand = Brand::where('slug', 'nike')->firstOrFail();
        $first = $brand->logo_path;
        $this->assertNotNull($first);
        Storage::disk(Media::disk())->assertExists($first);

        // Logo listede URL olarak gider
        $this->withoutVite();
        $this->actingAs($user)->get('/products/brands')
            ->assertInertia(fn ($page) => $page->where('brands.0.logoUrl', Media::url($first)));

        // Değiştir (PUT method spoofing + multipart) → eski dosya silinir
        $this->actingAs($user)->post("/products/brands/{$brand->id}", [
            '_method' => 'put', 'name' => 'Nike', 'slug' => 'nike', 'sort_order' => 1,
            'logo' => UploadedFile::fake()->image('nike2.webp', 200, 200),
        ])->assertRedirect();
        $second = $brand->fresh()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk(Media::disk())->assertMissing($first);

        // Logo alanı gönderilmeden güncelleme mevcut logoya dokunmaz
        $this->actingAs($user)->put("/products/brands/{$brand->id}", ['name' => 'Nike Inc', 'slug' => 'nike', 'sort_order' => 1])
            ->assertRedirect();
        $this->assertSame($second, $brand->fresh()->logo_path);

        // Kaldır
        $this->actingAs($user)->put("/products/brands/{$brand->id}", ['name' => 'Nike Inc', 'slug' => 'nike', 'sort_order' => 1, 'remove_logo' => 1])
            ->assertRedirect();
        $this->assertNull($brand->fresh()->logo_path);
        Storage::disk(Media::disk())->assertMissing($second);

        // Marka silinince logosu da silinir
        $this->actingAs($user)->post("/products/brands/{$brand->id}", [
            '_method' => 'put', 'name' => 'Nike Inc', 'slug' => 'nike', 'sort_order' => 1,
            'logo' => UploadedFile::fake()->image('nike3.jpg', 200, 200),
        ]);
        $third = $brand->fresh()->logo_path;
        $this->actingAs($user)->delete("/products/brands/{$brand->id}")->assertRedirect();
        Storage::disk(Media::disk())->assertMissing($third);
    }

    public function test_icon_is_saved_cleared_and_validated(): void
    {
        $user = $this->manager();

        $this->actingAs($user)->post('/products/brands', ['name' => 'Kendi', 'slug' => 'kendi', 'icon' => 'lab:shirt-long-sleeve'])
            ->assertRedirect();
        $brand = Brand::where('slug', 'kendi')->firstOrFail();
        $this->assertSame('lab:shirt-long-sleeve', $brand->icon);

        $this->actingAs($user)->put("/products/brands/{$brand->id}", ['name' => 'Kendi', 'slug' => 'kendi', 'icon' => ''])
            ->assertRedirect();
        $this->assertNull($brand->fresh()->icon);

        $this->actingAs($user)->put("/products/brands/{$brand->id}", ['name' => 'Kendi', 'slug' => 'kendi', 'icon' => '<svg>'])
            ->assertSessionHasErrors('icon');
    }

    public function test_logo_rejects_svg_and_large_files(): void
    {
        Storage::fake(Media::disk());
        $user = $this->manager();

        $this->actingAs($user)->post('/products/brands', [
            'name' => 'Svg', 'slug' => 'svg',
            'logo' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo');

        $this->actingAs($user)->post('/products/brands', [
            'name' => 'Big', 'slug' => 'big',
            'logo' => UploadedFile::fake()->image('big.png')->size(3000),
        ])->assertSessionHasErrors('logo');

        $this->assertSame(0, Brand::whereIn('slug', ['svg', 'big'])->count());
    }
}
