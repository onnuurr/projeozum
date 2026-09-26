<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use Modules\Product\Models\ProductVariant;
use Tests\TestCase;

class PushProductToBagistoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'bagisto.sync.base_url'       => 'https://bagisto.test',
            'bagisto.sync.webhook_secret' => 'shared-secret',
        ]);
    }

    public function test_handle_posts_upsert_payload_for_created_product(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $product = Product::factory()->create(['sku' => 'PRD-1']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'VAR-1']);

        (new PushProductToBagisto($product->id, 'created'))
            ->handle(app(ProductPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://bagisto.test/api/saas-sync/products'
                && $request['event'] === 'created'
                && $request['sku'] === 'PRD-1'
                && $request['variants'][0]['sku'] === 'VAR-1';
        });
    }

    public function test_upsert_payload_lists_cover_image_first(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id]);
        // Kapak sort_order'da sonda — images() ilişkisinin sabit sıralaması kapağı ezmemeli.
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x/a.png', 'sort_order' => 1, 'is_cover' => false]);
        ProductImage::create(['product_id' => $product->id, 'path' => 'products/x/cover.png', 'sort_order' => 2, 'is_cover' => true]);

        (new PushProductToBagisto($product->id, 'updated'))
            ->handle(app(ProductPayloadMapper::class), app(BagistoSyncClient::class));

        // Ürün fabrikası kategori de açar; kategori senkronu (senkron kuyrukta) ayrı bir istek atar.
        Http::assertSent(fn ($request) => str_ends_with($request->url(), 'api/saas-sync/products')
            && str_ends_with($request['images'][0], 'products/x/cover.png'));
    }

    public function test_handle_posts_minimal_payload_for_deleted_product(): void
    {
        Http::fake(['*' => Http::response(['message' => 'ok'])]);

        $product = Product::factory()->create(['sku' => 'PRD-2']);
        $product->delete();

        (new PushProductToBagisto($product->id, 'deleted'))
            ->handle(app(ProductPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertSent(function ($request) {
            return $request['event'] === 'deleted' && $request['sku'] === 'PRD-2' && ! isset($request['variants']);
        });
    }

    public function test_handle_skips_silently_when_product_missing(): void
    {
        Http::fake();

        (new PushProductToBagisto(999999, 'created'))
            ->handle(app(ProductPayloadMapper::class), app(BagistoSyncClient::class));

        Http::assertNothingSent();
    }
}
