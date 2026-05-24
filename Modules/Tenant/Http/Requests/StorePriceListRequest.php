<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('tenant.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'product_group_id' => 'nullable|integer',
            'discount_rate'    => 'required|numeric|min:0|max:100',
            'special_price'    => 'nullable|numeric|min:0',
            'valid_from'       => 'nullable|date',
            'valid_until'      => 'nullable|date|after_or_equal:valid_from',
            'is_active'        => 'boolean',
        ];
    }
}
