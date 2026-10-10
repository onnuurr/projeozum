<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\ProductDescriptionMaterial;
use Modules\Product\Services\ProductAttributeService;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product.add') ?? false;
    }

    public function rules(): array
    {
        $ignoreId = $this->ignoredProductId();

        $rules = [
            'name'              => ['required', 'string', 'max:191'],
            'sku'               => [
                'required',
                'string',
                'max:64',
                Rule::unique('products', 'sku')->ignore($ignoreId),
            ],
            'slug'              => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('products', 'slug')->ignore($ignoreId),
            ],
            'category_id'       => ['required', 'integer', Rule::exists('product_categories', 'id')],
            'brand_id'          => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'market_price'      => ['nullable', 'numeric', 'min:0'],
            'purchase_price'    => ['nullable', 'numeric', 'min:0'],
            // Bir siparişte bu üründen (tüm varyantlar) en fazla; boş = sınırsız. Bagisto sepeti uygular.
            'max_order_qty'     => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_new'            => ['nullable', 'boolean'],
            'is_featured'       => ['nullable', 'boolean'],
            'free_shipping'     => ['nullable', 'boolean'],
            'material'          => ['nullable', 'string', 'max:191'],
            'attributes'        => ['nullable', 'array'],

            // Açıklamalar (M2)
            'public_name'        => ['nullable', 'string', 'max:255'],
            'public_description' => ['nullable', 'string', 'max:10000'],
            'tenant_description' => ['nullable', 'string', 'max:10000'],

            // SEO
            'meta_title'        => ['nullable', 'string', 'max:191'],
            'meta_description'  => ['nullable', 'string', 'max:500'],
            'meta_keywords'     => ['nullable', 'string', 'max:255'],

            // Kargo
            'weight'            => ['nullable', 'numeric', 'min:0'],
            'desi'              => ['nullable', 'numeric', 'min:0'],
            // Kargo paketi ölçüleri (cm)
            'length_cm'         => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'width_cm'          => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'height_cm'         => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'shipping_time'     => ['nullable', 'string', 'max:50'],
            'shipping_fee'      => ['nullable', 'numeric', 'min:0'],

            // Diğer
            'barcode'           => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('products', 'barcode')->ignore($ignoreId),
            ],
            'is_domestic'       => ['nullable', 'boolean'],
            'manufacturer_code' => ['nullable', 'string', 'max:64'],
            'gtip_code'         => ['nullable', 'string', 'max:32'],

            'images'            => ['nullable', 'array', 'max:10'],
            'images.*'          => ['file', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'], // her görsel 5MB (webp dahil)
            // images ile aynı sırada: yüklenen fotoğrafın rengi (boş = bütün renkler) — Creative mokap o rengi bundan giydirir.
            'image_colors'      => ['nullable', 'array', 'max:10'],
            'image_colors.*'    => ['nullable', 'string', 'max:60'],

            'variants'                  => ['required', 'array', 'min:1'],
            'variants.*.size'           => ['nullable', 'string', 'max:16'],
            'variants.*.color_name'     => ['nullable', 'string', 'max:32'],
            'variants.*.color_hex'      => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'variants.*.sku'            => ['required', 'string', 'max:64', 'distinct'],
            'variants.*.price'          => ['required', 'numeric', 'min:0'],
            'variants.*.old_price'      => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock'          => ['required', 'integer', 'min:0'],

            // Ürün açıklaması için materyal bağı (M1).
            'description_materials'                 => ['nullable', 'array'],
            'description_materials.*.material_id'   => ['required_with:description_materials.*', 'integer', Rule::exists('materials', 'id')],
            'description_materials.*.role'          => ['required_with:description_materials.*', Rule::in(ProductDescriptionMaterial::ROLES)],
            'description_materials.*.sort_order'    => ['nullable', 'integer', 'min:0'],
            'description_materials.*.notes'         => ['nullable', 'string', 'max:500'],
        ];

        $categoryId = $this->input('category_id');

        return array_merge(
            $rules,
            app(ProductAttributeService::class)->rulesFor($categoryId ? (int) $categoryId : null),
        );
    }

    public function messages(): array
    {
        return [
            'variants.required'          => __('product::messages.validation.variants_required'),
            'variants.min'               => __('product::messages.validation.variants_required'),
            'variants.*.sku.distinct'    => __('product::messages.validation.variant_sku_distinct'),
            'variants.*.color_hex.regex' => __('product::messages.validation.color_hex_format'),
        ];
    }

    /**
     * Store'da benzersizlik kuralları hiçbir kaydı hariç tutmaz.
     * UpdateProductRequest güncellenen ürünü hariç tutmak için override eder.
     */
    protected function ignoredProductId(): ?int
    {
        return null;
    }
}
