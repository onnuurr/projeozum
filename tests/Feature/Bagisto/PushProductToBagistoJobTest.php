<?php

namespace Tests\Feature\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Product;
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
