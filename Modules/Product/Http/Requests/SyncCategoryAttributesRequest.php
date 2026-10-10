<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Kategoriler › Özellikler: kategorinin kendi global özellik seçimi (bütün liste). */
class SyncCategoryAttributesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('category.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'attributes'            => ['present', 'array'],
            'attributes.*.id'       => ['required', 'integer', 'distinct', 'exists:product_attributes,id'],
            'attributes.*.required' => ['nullable', 'boolean'],
        ];
    }
}
