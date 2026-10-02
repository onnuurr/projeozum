<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Product\Models\Category;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('category.edit') ?? false;
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
            'icon'       => ['nullable', 'string', 'max:64', 'regex:/^(?:(?:lab|oz):)?[a-z0-9-]+$/'], // ortak ikon kaydındaki ad (resources/js/lucideIcons.js)
            'parent_id'  => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'status'     => ['required', Rule::in(['active', 'passive'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Kategori kendisinin ya da kendi alt kategorisinin altına taşınamaz (ağaçta döngü oluşur). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $categoryId = $this->route('category')?->id;
                $parentId = $this->input('parent_id');
                if (! $categoryId || ! $parentId || $validator->errors()->has('parent_id')) {
                    return;
                }

                // Yeni üstten köke doğru yürü; yolda kategorinin kendisi çıkarsa döngü
                $seen = [];
                for ($id = (int) $parentId; $id && ! isset($seen[$id]); $id = (int) Category::whereKey($id)->value('parent_id')) {
                    if ($id === $categoryId) {
                        $validator->errors()->add('parent_id', __('product::messages.validation.parent_cycle'));

                        return;
                    }
                    $seen[$id] = true;
                }
            },
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
