<?php

namespace Modules\Creative\Services\Mockup;

use Modules\Creative\Models\MockupOutfit;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttributeOption;
use RuntimeException;

/**
 * Ürünün mokap bölge tipini ve boyunu çözer.
 *
 * Bölge: kategori adı → config('creative.mockup.category_regions') (eşleşmeyen kategori mokap
 * dışıdır). Boy: ürünün "Boy" özelliği → length_by_option; yoksa kategori varsayılanı; o da
 * yoksa null döner ve stilist tahmin eder (length_source=ai, uyarılı). Açık override'lar
 * (komut/ileride ayarlar) her şeyin önündedir.
 */
class MockupProductTypeResolver
{
    /**
     * @return array{region:string, length:?string, length_source:string}
     */
    public function resolve(Product $product, ?string $region = null, ?string $length = null): array
    {
        $map = null;
        if ($region === null) {
            $category = $product->category;
            if (! $category) {
                throw new RuntimeException(__('creative::messages.mockup.no_category'));
            }
            $map = config('creative.mockup.category_regions.' . $this->key($category->name));
            if (! is_array($map) || ! in_array($map['region'] ?? null, MockupOutfit::REGIONS, true)) {
                throw new RuntimeException(__('creative::messages.mockup.unsupported_category', ['category' => $category->name]));
            }
            $region = $map['region'];
        }

        if ($length !== null) {
            return ['region' => $region, 'length' => $length, 'length_source' => 'override'];
        }

        if ($fromAttribute = $this->lengthFromAttribute($product)) {
            return ['region' => $region, 'length' => $fromAttribute, 'length_source' => 'attribute'];
        }

        if (! empty($map['length'])) {
            return ['region' => $region, 'length' => $map['length'], 'length_source' => 'category'];
        }

        return ['region' => $region, 'length' => null, 'length_source' => 'ai'];
    }

    private function lengthFromAttribute(Product $product): ?string
    {
        $optionId = ($product->getAttribute('attributes') ?? [])[config('creative.mockup.length_attribute', 'boy')] ?? null;
        if (! $optionId || is_array($optionId)) {
            return null;
        }
        $name = ProductAttributeOption::whereKey($optionId)->value('name');

        return $name ? (config('creative.mockup.length_by_option.' . $this->key($name)) ?: null) : null;
    }

    private function key(string $name): string
    {
        // config anahtarlarında nokta olmaz; kategori/seçenek adlarında da yok — yine de güvenli tut.
        return str_replace('.', '', mb_strtolower(trim($name)));
    }
}
