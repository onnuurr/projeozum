<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('price-list.manage') ?? false;
    }

    public function rules(): array
    {
        // type güncellenmez — (variant_id, type) unique kimliğin parçasıdır.
        return [
            'price'     => ['required', 'numeric', 'min:0'],
            'currency'  => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
