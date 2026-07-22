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
            'gender'            => ['required', Rule::in(['Erkek', 'Kadın', 'Unisex'])],
            'market_price'      => ['nullable', 'numeric', 'min:0'],
            'purchase_price'    => ['nullable', 'numeric', 'min:0'],
            'is_new'            => ['nullable', 'boolean'],
            'free_shipping'     => ['nullable', 'boolean'],
            'care_instructions' => ['nullable', 'string', 'max:2000'],
            'material'          => ['nullable', 'string', 'max:191'],
            'origin_country'    => ['nullable', 'string', 'size:2'],
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
            'variants.required'          => 'En az bir varyant eklenmelidir.',
            'variants.min'               => 'En az bir varyant eklenmelidir.',
            'variants.*.sku.distinct'    => 'Varyant SKU\'ları benzersiz olmalıdır.',
            'variants.*.color_hex.regex' => 'Renk kodu #RRGGBB formatında olmalıdır.',
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
