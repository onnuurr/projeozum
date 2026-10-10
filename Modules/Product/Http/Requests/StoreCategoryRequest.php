<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('category.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:191'],
            'slug'       => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_categories', 'slug'),
            ],
            'icon'       => ['nullable', 'string', 'max:64', 'regex:/^(?:(?:lab|oz):)?[a-z0-9-]+$/'], // ortak ikon kaydındaki ad (resources/js/lucideIcons.js)
            // KDV %; boş = üst kategoriden miras. Yalnız Bagisto'da vergi kategorisi olan oranlar (kdv-<oran>).
            'vat_rate'   => ['nullable', 'numeric', 'in:0,1,10,20'],
            'parent_id'  => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'status'     => ['required', Rule::in(['active', 'passive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('product::messages.validation.slug_format'),
            'icon.regex' => __('product::messages.validation.icon_format'),
        ];
    }
}
