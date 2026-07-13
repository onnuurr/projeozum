<?php

namespace Modules\Marketplace\Services\Trendyol;

use Modules\Marketplace\DTOs\ProductPushDTO;

/**
 * ProductPushDTO → Trendyol pushProduct payload mapping.
 *
 * Trendyol "Ürün Bilgileri Aktarımı" v2 endpoint'i şu şemayı bekler (özet):
 *   { "items":[ {"barcode":"...", "title":"...", "productMainId":"...", "brandId":N,
 *                "categoryId":N, "quantity":N, "stockCode":"...", "listPrice":N,
 *                "salePrice":N, "vatRate":N, "images":[{"url":"..."}], "attributes":[...] } ] }
 *
 * NOT: brandId/categoryId ve images çağıran modül (Tenant) tarafından DTO üzerinde
 * zaten çözülmüş halde gelir (bkz. TrendyolProductPayloadBuilder) — bu mapper hiçbir
 * DB sorgusu yapmaz, sadece DTO'yu wire formatına çevirir.
 */
class TrendyolProductMapper
{
    public function toPushPayload(ProductPushDTO $product): array
    {
        $variants = $product->variants !== [] ? $product->variants : [null];

        $items = [];
        foreach ($variants as $variant) {
            $items[] = [
                'barcode'        => $variant?->sku ?? $product->sku ?? (string) $product->productId,
                'title'          => $product->title,
                'productMainId'  => $product->sku ?? (string) $product->productId,
                'brandId'        => $product->externalBrandId,
                'categoryId'     => $product->externalCategoryId,
                'quantity'       => $variant?->stock ?? 0,
                'stockCode'      => $variant?->sku ?? $product->sku,
                'listPrice'      => $product->listPrice,
                'salePrice'      => $variant?->price ?? $product->salePrice,
                'vatRate'        => $product->vatRate,
                'images'         => array_map(fn (string $url) => ['url' => $url], $product->images),
                'attributes'     => array_filter([
                    ($variant?->colorName ?? '') !== '' ? ['attributeId' => 47, 'customAttributeValue' => $variant->colorName] : null,
                    ($variant?->size ?? '') !== ''      ? ['attributeId' => 338, 'customAttributeValue' => $variant->size]      : null,
                ]),
            ];
        }

        return ['items' => $items];
    }
}
