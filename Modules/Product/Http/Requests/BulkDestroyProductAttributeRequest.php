<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Ürün Ayarları › Özellikler toplu silme. */
class BulkDestroyProductAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product-attribute.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:product_attributes,id'],
        ];
    }
}
