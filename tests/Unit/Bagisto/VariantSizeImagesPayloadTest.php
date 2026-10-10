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

        // Genel galeri: genel görsel + varsayılan set (ilk bedene özel görselin seti: 7 Yaş), SaaS sırasıyla.
        $this->assertCount(2, $p['images']);
        $this->assertStringEndsWith('p/plain.jpg', $p['images'][0]);
        $this->assertStringEndsWith('p/small.webp', $p['images'][1]);
        $this->assertSame([], $bySize['7 Yaş']['images']);                    // seti zaten genel galeride
        $this->assertCount(1, $bySize['14 Yaş']['images']);
        $this->assertStringEndsWith('p/big.webp', $bySize['14 Yaş']['images'][0]);
        $this->assertSame($bySize['11 Yaş']['images'], $bySize['14 Yaş']['images']);
    }

    public function test_color_specific_set_wins_over_the_general_set(): void
    {
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'G', 'size' => '7 Yaş', 'color_name' => 'Yeşil']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'B', 'size' => '7 Yaş', 'color_name' => 'Bej']);
        $product->images()->create(['path' => 'p/general.webp', 'sort_order' => 1, 'variant_sizes' => ['7 Yaş']]);
        $product->images()->create(['path' => 'p/green.webp', 'sort_order' => 2, 'variant_sizes' => ['7 Yaş'], 'variant_colors' => ['Yeşil']]);

        $bySku = collect($this->payload($product)['variants'])->keyBy('sku');

        $this->assertCount(1, $bySku['G']['images']);
        $this->assertStringEndsWith('p/green.webp', $bySku['G']['images'][0]);      // rengin kendi seti
        $this->assertSame([], $bySku['B']['images']);                               // Bej'in seti yok → genel (galeride)
    }

    public function test_cover_mockup_set_leads_the_general_gallery_without_duplicates(): void
    {
        // Ürün #11 biçimi: tek manken seti bütün bedenleri kapsıyor, kapak bir mokap; push kapağı başa alır.
        $product = Product::factory()->create();
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'K5', 'size' => '5 Yaş']);
        $sizes = ['3 Yaş', '5 Yaş', '12 Yaş'];
        $product->images()->create(['path' => 'p/plain.webp', 'sort_order' => 3]);
        $product->images()->create(['path' => 'p/m2.webp', 'sort_order' => 2, 'variant_sizes' => $sizes]);
        $product->images()->create(['path' => 'p/m1.webp', 'sort_order' => 1, 'variant_sizes' => $sizes, 'is_cover' => true]);

        $loaded = $product->fresh(['variants', 'category',
            'images' => fn ($q) => $q->reorder()->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id')]);
        $p = app(ProductPayloadMapper::class)->toUpsertPayload($loaded, 'updated');

        $this->assertSame(['m1.webp', 'm2.webp', 'plain.webp'], array_map('basename', $p['images']));
        $this->assertSame([], $p['variants'][0]['images']);   // önceki varyant kopyası temizlenir, tekrar yok
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
