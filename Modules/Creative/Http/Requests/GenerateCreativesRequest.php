<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'format'        => ['sometimes', 'string', Rule::in(array_keys((array) config('creative.formats', [])))],
            'pose'          => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
