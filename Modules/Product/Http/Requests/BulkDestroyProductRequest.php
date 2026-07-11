<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkDestroyProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product.delete') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
