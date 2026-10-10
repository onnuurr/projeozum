<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductColorRequest extends FormRequest
{
    protected string $permission = 'variant-option.create';

    public function authorize(): bool
    {
        return $this->user()?->can($this->permission) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'hex'  => strtolower(trim((string) $this->input('hex'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:100', Rule::unique('product_colors', 'name')->ignore($this->ignoredId())],
            'hex'        => ['required', 'regex:/^#[0-9a-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'hex.regex' => __('product::messages.validation.hex_format'),
        ];
    }

    /** Store'da ignore yok; UpdateProductColorRequest güncellenen kaydı hariç tutar. */
    protected function ignoredId(): ?int
    {
        return null;
    }
}
