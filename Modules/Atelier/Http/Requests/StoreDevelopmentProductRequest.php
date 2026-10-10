<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductAttribute;

/**
 * "Üretilecek Ürün Ekle": reçete + maliyetten geçici stok kartı açma. Hem ürün açma
 * (product.add) hem reçete yönetme (atelier.bom.manage) yetkisi gerekir.
 */
class StoreDevelopmentProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('product.add') && $user->can('atelier.bom.manage');
    }

    public function rules(): array
    {
        return [
            // Kimlik
            'name'        => ['required', 'string', 'max:191'],
            'sku'         => ['required', 'string', 'max:64', Rule::unique('products', 'sku')],
            'category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')],
            'brand_id'    => ['nullable', 'integer', Rule::exists('brands', 'id')],
            // Ürün Ayarları → Özellikler'deki `cinsiyet` seçeneğinin id'si
            'gender'      => ['required', 'integer', Rule::exists('product_attribute_options', 'id')->where('product_attribute_id', ProductAttribute::where('code', Product::GENDER_ATTRIBUTE)->value('id'))],

            // Varyantlar (stok 0 açılır — stok iş emri tamamlanınca girer)
            'variants'              => ['required', 'array', 'min:1'],
            'variants.*.size'       => ['nullable', 'string', 'max:16'],
            'variants.*.color_name' => ['nullable', 'string', 'max:32'],
            'variants.*.color_hex'  => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
            'variants.*.sku'        => ['required', 'string', 'max:64', 'distinct', Rule::unique('product_variants', 'sku')],

            // Reçete
            'bom_name'                  => ['nullable', 'string', 'max:191'],
            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.material_id'       => ['required', 'integer', 'distinct', Rule::exists('materials', 'id')],
            'lines.*.quantity_per_unit' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.waste_pct'         => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Maliyet / fiyat
            'extra_cost_per_unit' => ['nullable', 'numeric', 'min:0'],
            'margin_pct'          => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'price'               => ['required', 'numeric', 'min:0'],
        ] + UpdateProductRouteRequest::stepRules('route', required: false);
    }

    public function messages(): array
    {
        return UpdateProductRouteRequest::stepMessages('route');
    }

    public function attributes(): array
    {
        return [
            'variants.*.sku'            => 'varyant SKU',
            'lines.*.material_id'       => 'hammadde',
            'lines.*.quantity_per_unit' => 'birim miktar',
            'price'                     => 'satış fiyatı',
        ];
    }
}
