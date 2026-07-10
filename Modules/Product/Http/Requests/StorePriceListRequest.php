<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\PriceList;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('price-list.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'type'      => ['required', Rule::in([
                PriceList::TYPE_RETAIL,
                PriceList::TYPE_DEALER,
                PriceList::TYPE_DROPSHIP,
            ])],
            'price'     => ['required', 'numeric', 'min:0'],
            'currency'  => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
