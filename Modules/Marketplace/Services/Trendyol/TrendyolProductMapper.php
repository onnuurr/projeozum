<?php

namespace Modules\Marketplace\Services\Trendyol;

use Modules\Product\Models\Product;

/**
 * Product → Trendyol pushProduct payload mapping.
 *
 * Trendyol "Ürün Bilgileri Aktarımı" v2 endpoint'i şu şemayı bekler (özet):
 *   { "items":[ {"barcode":"...", "title":"...", "productMainId":"...", "brandId":N,
 *                "categoryId":N, "quantity":N, "stockCode":"...", "listPrice":N,
 *                "salePrice":N, "vatRate":N, "images":[{"url":"..."}], "attributes":[...] } ] }
 *
 * NOT: brandId / categoryId Trendyol'un kendi katalogundan; CategoryMarketplaceMapping
 * tablosu üzerinden çözülür (yoksa fallback olarak title-based matching deneyebiliriz).
 */
class TrendyolProductMapper
{
    public function toPushPayload(Product $product, ?int $trendyolCategoryId, ?int $trendyolBrandId): array
    {
        $variants = $product->variants->isNotEmpty() ? $product->variants : collect([null]);

        $items = [];
        foreach ($variants as $variant) {
            $items[] = [
                'barcode'        => $variant?->sku ?? $product->sku ?? $product->id,
                'title'          => $product->name,
                'productMainId'  => $product->sku ?? (string) $product->id,
                'brandId'        => $trendyolBrandId,
                'categoryId'     => $trendyolCategoryId,
                'quantity'       => (int) ($variant?->stock ?? 0),
                'stockCode'      => $variant?->sku ?? $product->sku,
                'listPrice'      => (float) ($product->old_price ?? $product->price),
                'salePrice'      => (float) ($variant?->price ?? $product->price),
                'vatRate'        => 18,
                'images'         => [['url' => "https://picsum.photos/seed/tek-p{$product->id}/800/1000"]],
                'attributes'     => array_filter([
                    $variant?->color_name ? ['attributeId' => 47, 'customAttributeValue' => $variant->color_name] : null,
                    $variant?->size       ? ['attributeId' => 338, 'customAttributeValue' => $variant->size]      : null,
                ]),
            ];
        }

        return ['items' => $items];
    }
}
