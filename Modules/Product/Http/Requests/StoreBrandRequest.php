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
            'icon'        => ['nullable', 'string', 'max:64', 'regex:/^(?:(?:lab|oz):)?[a-z0-9-]+$/'], // ortak ikon kaydındaki ad (resources/js/lucideIcons.js)
            // SVG bilerek yok: aynı origin'den servis edilen SVG içine script gömülebilir.
            'logo'        => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('product::messages.validation.slug_format'),
            'icon.regex' => __('product::messages.validation.icon_format'),
        ];
    }

    /** Store'da ignore yok; UpdateBrandRequest güncellenen markayı hariç tutar. */
    protected function ignoredBrandId(): ?int
    {
        return null;
    }
}
