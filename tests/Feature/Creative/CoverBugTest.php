<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Regresyon: Product::images() ilişkisine gömülü ->orderBy('sort_order'),
 * her yerde eklenen ->orderByDesc('is_cover') closure'larını sessizce
 * etkisiz kılıyordu (Laravel ORDER BY'ları eklemeli uyguluyor, ilişkinin
 * kendi sırası her zaman önce geliyordu) — bu yüzden Tryon'da "Kapak yap"
 * DB'de is_cover'ı doğru değiştirse bile ürün listesi/düzenleme sayfası
 * hâlâ eski kapağı gösteriyordu. Fix: ilgili closure'lara ->reorder() eklendi
 * (bkz. ProductController, ProductCatalogPresenter, TryonController,
 * ProductMarketplaceListingController).
 */
class CoverBugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'creative.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.approve', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'creative.asset.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
    }

    public function test_cover_reflects_on_product_edit_and_index_pages(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('creative.view', 'creative.approve', 'creative.asset.manage', 'product.add');

        $product = Product::factory()->create();
        $original = $product->images()->create(['path' => 'products/orig.png', 'sort_order' => 1, 'is_cover' => true]);

        $pose = Pose::create(['pose_key' => 'stand', 'label' => 'Ayakta', 'prompt' => 'standing', 'status' => Pose::STATUS_READY]);
        $staged = sprintf('products/%d/onmodel_staged_test.png', $product->id);
        Storage::disk('public')->put($staged, 'fake-bytes');

        $r = TryonResult::create([
            'product_id' => $product->id,
            'pose_id' => $pose->id,
            'status' => TryonResult::STATUS_DONE,
            'staged_image_path' => $staged,
            'review_status' => TryonResult::REVIEW_PENDING,
        ]);

        $this->actingAs($user)->post("/creative/tryon/{$r->id}/approve")->assertRedirect();
        $r->refresh();
        $this->actingAs($user)->post("/creative/tryon/{$r->id}/cover")->assertRedirect();

        $newCover = ProductImage::find($r->product_image_id);

        $editResp = $this->actingAs($user)->get("/products/{$product->id}/edit")->assertOk();
        $editImages = $editResp->viewData('page')['props']['product']['images'];
        $this->assertSame($newCover->id, $editImages[0]['id'], 'Edit sayfasında ilk sıradaki görsel kapak olmalı');
        $this->assertTrue($editImages[0]['is_cover']);

        $indexResp = $this->actingAs($user)->get('/products')->assertOk();
        $indexProducts = $indexResp->viewData('page')['props']['products'];
        $row = collect($indexProducts)->firstWhere('id', $product->id);
        $this->assertSame($newCover->url, $row['image'], 'Ürün listesindeki thumbnail yeni kapak olmalı');
    }
}
