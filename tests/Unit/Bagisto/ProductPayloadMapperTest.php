<?php

namespace Tests\Unit\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Tests\TestCase;

/**
 * Faz 1: SaaS ürün/varyant modelinden, Bagisto'daki
 * `Webkul\SaasSync\Http\Controllers\ProductWebhookController::handle()`
 * validasyonunun beklediği payload şekline doğru çevrildiğini doğrular.
 */
class ProductPayloadMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_payload_maps_variants_with_color_and_size(): void
    {
        $category = Category::factory()->create(['slug' => 'tisort']);
        $product  = Product::factory()->create(['category_id' => $category->id, 'sku' => 'PRD-1']);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku'        => 'VAR-1',
            'price'      => 199.90,
            'stock'      => 12,
            'color_name' => 'Kırmızı',
            'size'       => 'M',
        ]);

        $payload = app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'created');

        $this->assertSame('created', $payload['event']);
        $this->assertSame('PRD-1', $payload['sku']);
        $this->assertSame(['tisort'], $payload['category_codes']);
        $this->assertCount(1, $payload['variants']);
        $this->assertSame('VAR-1', $payload['variants'][0]['sku']);
        $this->assertSame(199.90, $payload['variants'][0]['price']);
        $this->assertSame(12, $payload['variants'][0]['quantity']);
        $this->assertSame('Kırmızı', $payload['variants'][0]['color']);
        $this->assertSame('M', $payload['variants'][0]['size']);
    }

    public function test_delete_payload_only_carries_event_and_sku(): void
    {
        $product = Product::factory()->create(['sku' => 'PRD-9']);

        $payload = app(ProductPayloadMapper::class)->toDeletePayload($product);

        $this->assertSame(['event' => 'deleted', 'sku' => 'PRD-9'], $payload);
    }
}
