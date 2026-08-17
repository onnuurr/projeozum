<?php

namespace Modules\Bagisto\Mappers;

use Modules\Product\Models\Product;

/**
 * `Product` + varyantlarını, Bagisto tarafındaki
 * `Webkul\SaasSync\Http\Controllers\ProductWebhookController::handle()`
 * validasyonunun beklediği payload şekline çevirir.
 *
 * Her varyant her zaman gönderilir (renk/beden boş olsa bile) — SaaS'ta bir
 * ürün asla varyantsız olmadığı için (bkz. `ProductService::create()`),
 * Bagisto tarafında da her zaman configurable akış kullanılır.
 */
class ProductPayloadMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toUpsertPayload(Product $product, string $event): array
    {
        return [
            'event'                 => $event,
            'sku'                   => $product->sku,
            'name'                  => $product->name,
            'weight'                => $product->weight !== null ? (float) $product->weight : null,
            'short_description'     => $product->public_description,
            'description'           => $product->tenant_description ?? $product->public_description,
            'status'                => $product->status === Product::STATUS_PUBLISHED,
            'category_codes'        => $product->category ? [$product->category->slug] : [],
            'category_name'         => $product->category?->name,
            'images'                => $product->images->pluck('url')->all(),
            'variants'              => $product->variants->map(fn ($variant) => [
                'sku'      => $variant->sku,
                'price'    => (float) $variant->price,
                'quantity' => (int) $variant->stock,
                'color'    => $variant->color_name,
                'size'     => $variant->size,
                'images'   => $variant->images->pluck('url')->all() ?: null,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDeletePayload(Product $product): array
    {
        return [
            'event' => 'deleted',
            'sku'   => $product->sku,
        ];
    }
}
