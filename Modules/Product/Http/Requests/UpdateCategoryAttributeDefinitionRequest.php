<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryAttributeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('category.manage') ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;
        $definitionId = $this->route('definition')?->id;

        return [
            'key'        => [
                'required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('category_attribute_definitions', 'key')
                    ->where('category_id', $categoryId)
                    ->ignore($definitionId),
            ],
            'label'      => ['required', 'string', 'max:120'],
            'type'       => ['required', Rule::in(['string', 'enum', 'number', 'boolean'])],
            'options'    => ['nullable', 'array'],
            'options.*'  => ['string', 'max:120'],
            'required'   => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('product::messages.validation.attribute_key_format'),
        ];
    }
}
