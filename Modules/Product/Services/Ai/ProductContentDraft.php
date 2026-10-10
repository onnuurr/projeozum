<?php

namespace Modules\Product\Services\Ai;

use Modules\Product\Models\Brand;
use Modules\Product\Models\Category;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductAttributeService;

/**
 * Ürün formundaki (henüz kaydedilmemiş) bilgilerden AI üretimi için KAYDEDİLMEYEN bir Product
 * kurar: AI butonu kayıt beklemeden, formda o an yazılı olan ad/marka/kategori/özellik/kumaş
 * bilgisiyle çalışır (yıkama talimatı kategori özelliği olarak gelir). Düzenlemede id korunur (reçete materyali yedeği ve audit için) ama model
 * `exists` olsa bile asla save edilmez — controller yalnız ai_generated_at'i kayıtlı üründe işler.
 */
class ProductContentDraft
{
    public function __construct(private readonly ProductAttributeService $attributes) {}

    /** @param  array<string, mixed>  $data  GenerateProductContentRequest::validated() */
    public function make(array $data, ?Product $existing = null): Product
    {
        $categoryId = (int) $data['category_id'];

        $draft = new Product;
        if ($existing) {
            $draft->setAttribute('id', $existing->id);
        }

        $draft->forceFill([
            'name'              => trim($data['name']),
            'category_id'       => $categoryId,
            'brand_id'          => $data['brand_id'] ?? null,
            'material'          => $data['material'] ?? null,
            'attributes'        => $this->attributes->normalize($categoryId, $data['attributes'] ?? []),
        ]);

        $draft->setRelation('category', Category::with('parent')->find($categoryId));
        $draft->setRelation('brand', isset($data['brand_id']) ? Brand::find($data['brand_id']) : null);

        return $draft;
    }
}
