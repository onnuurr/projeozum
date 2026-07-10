<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Product\Models\Warehouse;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('warehouse.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:191'],
            'code'      => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('warehouses', 'code')->ignore($this->ignoredWarehouseId()),
            ],
            'address'   => ['nullable', 'string', 'max:1000'],
            'city'      => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Depo kodu yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        ];
    }

    /** Store'da ignore yok; UpdateWarehouseRequest güncellenen depoyu hariç tutar. */
    protected function ignoredWarehouseId(): ?int
    {
        return null;
    }
}
