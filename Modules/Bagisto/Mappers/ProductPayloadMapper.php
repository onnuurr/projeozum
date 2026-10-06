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
            'attribute_family_code' => config('bagisto.sync.attribute_family_code'),
            'name'                  => $product->name,
            'weight'                => $product->weight !== null ? (float) $product->weight : null,
            'short_description'     => $product->public_description,
            'description'           => $product->tenant_description ?? $product->public_description,
            'status'                => $product->status === Product::STATUS_PUBLISHED,
            'category_codes'        => $product->category ? [$product->category->slug] : [],
            'category_name'         => $product->category?->name,
            // Bedene özel görseller (variant_sizes dolu — Creative mokap beden setleri) genel galeriye
            // girmez; yalnız o bedenlerin varyantlarına gider (mağazada beden seçilince galeri değişir).
            'images'                => $product->images->filter(fn ($img) => $img->variant_sizes === null)->pluck('url')->values()->all(),
            'variants'              => $product->variants->map(fn ($variant) => [
                'sku'      => $variant->sku,
                'price'    => (float) $variant->price,
                'quantity' => (int) $variant->stock,
                'color'    => $variant->color_name,
                'size'     => $variant->size,
                'images'   => $this->variantImages($product, $variant),
            ])->all(),
        ];
    }

    /**
     * Varyantın görselleri: doğrudan bağlı olanlar + bedeni variant_sizes'ta geçen bedene özel görseller.
     * Renk: varyantın rengine özel set (variant_colors) varsa o, yoksa genel set (variant_colors null).
     * Bedene özel görseli olan üründe liste boşsa [] gider (Bagisto varyantın eski setini temizler);
     * hiç bedene özel görseli olmayan üründe null (Bagisto mevcut görsellere dokunmaz — eski davranış).
     *
     * @param  \Modules\Product\Models\ProductVariant  $variant
     * @return list<string>|null
     */
    private function variantImages(Product $product, $variant): ?array
    {
        $scoped = $product->images->filter(fn ($img) => $img->variant_sizes !== null);
        $forSize = $scoped->filter(fn ($img) => in_array($variant->size, (array) $img->variant_sizes, true));
        $exact = $forSize->filter(fn ($img) => in_array($variant->color_name, (array) $img->variant_colors, true));
        $sets = $exact->isNotEmpty() ? $exact : $forSize->filter(fn ($img) => $img->variant_colors === null);
        $urls = $product->images->filter(fn ($img) => $img->product_variant_id === $variant->id)
            ->merge($sets)->sortBy('sort_order')->pluck('url')->unique()->values()->all();

        return $urls !== [] ? $urls : ($scoped->isNotEmpty() ? [] : null);
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
