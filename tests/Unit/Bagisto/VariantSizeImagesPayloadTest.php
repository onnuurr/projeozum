<?php

namespace Tests\Unit\Bagisto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Tests\TestCase;

/**
 * Bedene özel görseller (product_images.variant_sizes — Creative mokap beden setleri) mağazada beden
 * seçilince gösterilsin diye genel galeriye değil, o bedenlerin varyantlarına gider.
 */
class VariantSizeImagesPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Product $product): array
    {
        return app(ProductPayloadMapper::class)->toUpsertPayload($product->fresh(['variants', 'images', 'category']), 'updated');
    }

    public function test_size_scoped_images_go_only_to_matching_variants(): void
    {
        $product = Product::factory()->create();
        foreach (['7 Yaş', '11 Yaş', '14 Yaş'] as $i => $size) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => "V{$i}", 'size' => $size]);
        }
        $product->images()->create(['path' => 'p/plain.jpg', 'sort_order' => 0]);
        $product->images()->create(['path' => 'p/small.webp', 'sort_order' => 1, 'variant_sizes' => ['7 Yaş']]);
        $product->images()->create(['path' => 'p/big.webp', 'sort_order' => 2, 'variant_sizes' => ['11 Yaş', '14 Yaş']]);
        $product->images()->create(['path' => 'p/old.webp', 'sort_order' => 3, 'variant_sizes' => []]);   // seti devredilmiş

        $p = $this->payload($product);
        $bySize = collect($p['variants'])->keyBy('size');

        $this->assertCount(1, $p['images']);                                   // yalnız genel görsel
        $this->assertStringEndsWith('p/plain.jpg', $p['images'][0]);
        $this->assertCount(1, $bySize['7 Yaş']['images']);
        $this->assertStringEndsWith('p/small.webp', $bySize['7 Yaş']['images'][0]);
        $this->assertStringEndsWith('p/big.webp', $bySize['14 Yaş']['images'][0]);
        $this->assertSame($bySize['11 Yaş']['images'], $bySize['14 Yaş']['images']);
    }

    public function test_variant_without_its_set_gets_an_explicit_empty_list_only_when_sets_exist(): void
    {
        $plain = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $plain->id, 'sku' => 'A1', 'size' => '5 Yaş']);
        $this->assertNull($this->payload($plain)['variants'][0]['images']);    // eski davranış: dokunma

        $scoped = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $scoped->id, 'sku' => 'B1', 'size' => '5 Yaş']);
        $scoped->images()->create(['path' => 'p/x.webp', 'sort_order' => 0, 'variant_sizes' => ['9 Yaş']]);
        $this->assertSame([], $this->payload($scoped)['variants'][0]['images']);   // Bagisto eski seti temizler
    }
}
