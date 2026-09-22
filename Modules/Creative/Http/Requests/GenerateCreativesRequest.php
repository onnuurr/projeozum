<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Creative\Services\Ai\Support\CreativeSpec;

class GenerateCreativesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'template_id'   => ['required', 'integer', 'exists:creative_templates,id'],
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'use_ai'        => ['sometimes', 'boolean'],
            'use_copy_ai'   => ['sometimes', 'boolean'],
            'render_engine' => ['sometimes', 'string', Rule::in(['svg', 'ai_compose'])],
            'format'        => ['sometimes', 'string', Rule::in(array_keys((array) config('creative.formats', [])))],
            'pose'          => ['sometimes', 'nullable', 'string', 'max:500'],
            // Creative Rules Engine kanalı — yalnız use_ai (svg+AI sahne) yolunda
            // anlamlı, ai_compose (Faz J) bundan bağımsız kalır.
            'channel'             => ['sometimes', 'nullable', 'string', Rule::in([
                CreativeSpec::CHANNEL_BRAND_POST,
                CreativeSpec::CHANNEL_PARTNER_SHARE,
                CreativeSpec::CHANNEL_CAMPAIGN,
            ])],
            'partner_brand_kit_id' => ['sometimes', 'nullable', 'integer', 'exists:partner_brand_kits,id'],
        ];
    }
}
