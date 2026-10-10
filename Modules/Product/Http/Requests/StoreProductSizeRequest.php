<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductSizeRequest extends FormRequest
{
    protected string $permission = 'variant-option.create';

    public function authorize(): bool
    {
        return $this->user()?->can($this->permission) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:50', Rule::unique('product_sizes', 'name')->ignore($this->ignoredId())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** Store'da ignore yok; UpdateProductSizeRequest güncellenen kaydı hariç tutar. */
    protected function ignoredId(): ?int
    {
        return null;
    }
}
