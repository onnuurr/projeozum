<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('portal.checkout') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')],
            'color'      => ['nullable', 'string', 'max:32'],
            'size'       => ['nullable', 'string', 'max:16'],
            'qty'        => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }
}
