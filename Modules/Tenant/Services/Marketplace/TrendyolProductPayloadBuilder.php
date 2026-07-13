<?php

namespace Modules\Tenant\Services\Marketplace;

use Illuminate\Support\Facades\DB;
use Modules\Marketplace\DTOs\ProductPushDTO;
use Modules\Marketplace\DTOs\ProductVariantPushDTO;
use Modules\Product\Models\Product;

/**
 * Product Eloquent modelini Marketplace modülünün anlayacağı saf ProductPushDTO'ya çevirir.
 *
 * Trendyol kategori/marka ID eşlemesi (category_marketplace_mappings) burada çözülür —
 * bu sorgu Product modülünün tablolarına bağımlı olduğu için Marketplace modülünde değil,
 * Tenant'ın bu builder'ında yaşar (eski TrendyolService::resolveCategoryMapping()'in taşınmış hali).
 */
class TrendyolProductPayloadBuilder
{
    public function build(Product $product): ProductPushDTO
    {
        $product->loadMissing(['variants', 'category', 'brand']);

        $mapping = $this->resolveCategoryMapping((int) $product->category_id);

        $variants = $product->variants->map(fn ($variant) => new ProductVariantPushDTO(
            sku: $variant->sku,
            stock: (int) ($variant->stock ?? 0),
            price: $variant->price !== null ? (float) $variant->price : null,
            colorName: $variant->color_name,
            size: $variant->size,
        ))->all();

        return new ProductPushDTO(
            productId: $product->id,
            sku: $product->sku,
            title: $product->name,
            listPrice: (float) ($product->old_price ?? $product->price),
            salePrice: (float) $product->price,
            vatRate: 18,
            images: ["https://picsum.photos/seed/tek-p{$product->id}/800/1000"],
            variants: $variants,
            externalCategoryId: isset($mapping['external_id']) ? (int) $mapping['external_id'] : null,
            externalBrandId: null,
        );
    }

    private function resolveCategoryMapping(int $localCategoryId): array
    {
        if ($localCategoryId === 0) {
            return [];
        }

        $row = DB::table('category_marketplace_mappings')
            ->join('marketplaces', 'marketplaces.id', '=', 'category_marketplace_mappings.marketplace_id')
            ->where('category_id', $localCategoryId)
            ->where('marketplaces.key', 'trendyol')
            ->select('category_marketplace_mappings.external_id', 'category_marketplace_mappings.category_path')
            ->first();

        return $row ? (array) $row : [];
    }
}
