<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\ProductDescriptionMaterial;
use Modules\Product\Services\Ai\ProductDescriptionPromptBuilder;

/**
 * AI ürün içeriği (açıklama / SEO): formdaki KAYDEDİLMEMİŞ bilgilerle çalışır. product_id yalnız
 * düzenlemede gelir (reçete materyali yedeği + ai_generated_at audit'i); yeni üründe boştur.
 */
class GenerateProductContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product.ai.generate') ?? false;
    }

    public function rules(): array
    {
        return [
            'parts'       => ['required', 'array', 'min:1'],
            'parts.*'     => ['string', Rule::in(ProductDescriptionPromptBuilder::PARTS)],
            'product_id'  => ['nullable', 'integer', Rule::exists('products', 'id')],
            'tenant_id'   => ['nullable', 'integer', Rule::exists('tenants', 'id')],
            'name'        => ['required', 'string', 'max:191'],
            'category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')],
            'brand_id'    => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'material'          => ['nullable', 'string', 'max:191'],
            // Değerler ProductContentDraft'ta kategoriye göre whitelist'lenir (ProductAttributeService::normalize).
            'attributes'  => ['nullable', 'array'],
            'description_materials'               => ['nullable', 'array', 'max:20'],
            'description_materials.*.material_id' => ['required', 'integer', Rule::exists('materials', 'id')],
            'description_materials.*.role'        => ['required', Rule::in(ProductDescriptionMaterial::ROLES)],
            'description_materials.*.sort_order'  => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => __('product::messages.ai.draft_incomplete'),
            'category_id.required' => __('product::messages.ai.draft_incomplete'),
        ];
    }
}
