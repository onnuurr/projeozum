<?php

namespace Tests\Feature\Bagisto;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Yeni ürün Taslak (pasif) başlar ve yayınlanana kadar Bagisto'ya gitmez; yayından kalkan ürün gitmeye devam eder. */
class DraftProductNotPushedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bagisto.sync.base_url'       => 'https://bagisto.test',
            'bagisto.sync.webhook_secret' => 'shared-secret',
        ]);
        Http::fake(['*' => Http::response(['message' => 'ok'])]);
    }

    private function push(Product $product): void
    {
        (new PushProductToBagisto($product->id, 'updated'))
            ->handle(app(ProductPayloadMapper::class), app(BagistoSyncClient::class));
    }

    private function productPushes(): int
    {
        return collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/api/saas-sync/products'))->count();
    }

    public function test_product_created_from_the_form_starts_as_draft(): void
    {
        Permission::firstOrCreate(['name' => 'product.add', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('product.add');
        $category = Category::create(['name' => 'Etek', 'slug' => 'etek-' . uniqid(), 'status' => 'active', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Yeni', 'sku' => 'YN-1', 'category_id' => $category->id,
            'variants' => [['sku' => 'YN-1-7', 'price' => 100, 'stock' => 1, 'size' => '7 Yaş']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(Product::STATUS_DRAFT, Product::where('sku', 'YN-1')->value('status'));
    }

    public function test_never_published_draft_is_not_pushed_until_published(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);

        $this->push($product);
        $this->assertSame(0, $this->productPushes());

        app(ProductService::class)->transitionStatus($product, Product::STATUS_PUBLISHED, User::factory()->create());
        $this->push($product->fresh());
        $this->assertGreaterThan(0, $this->productPushes());
    }

    public function test_unpublished_product_keeps_being_pushed_so_the_store_deactivates_it(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_PUBLISHED]);   // geçmiş satırı yok (eski ürünler gibi)

        app(ProductService::class)->transitionStatus($product, Product::STATUS_DRAFT, User::factory()->create());
        $this->push($product->fresh());

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/saas-sync/products') && $request['status'] === false);
    }
}
