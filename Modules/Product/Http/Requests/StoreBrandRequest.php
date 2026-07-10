<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\Brand;

class StoreBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('brand.manage') ?? false;
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
                Rule::unique('brands', 'slug')->ignore($this->ignoredBrandId()),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug yalnızca küçük harf, rakam ve tire içerebilir.',
        ];
    }

    /** Store'da ignore yok; UpdateBrandRequest güncellenen markayı hariç tutar. */
    protected function ignoredBrandId(): ?int
    {
        return null;
    }
}
