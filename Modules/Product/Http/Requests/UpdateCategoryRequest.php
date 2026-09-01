<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('category.manage') ?? false;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')?->id;

        return [
            'name'       => ['required', 'string', 'max:191'],
            'slug'       => [
                'required',
                'string',
                'max:191',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_categories', 'slug')->ignore($categoryId),
            ],
            'icon'       => ['nullable', 'string', 'max:16'],
            'parent_id'  => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'status'     => ['required', Rule::in(['active', 'passive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug yalnızca küçük harf, rakam ve tire içerebilir.',
        ];
    }
}
