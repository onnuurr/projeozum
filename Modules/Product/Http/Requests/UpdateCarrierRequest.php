<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('carrier.manage') ?? false;
    }

    public function rules(): array
    {
        $carrierId = $this->route('carrier')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('carriers', 'code')->ignore($carrierId)->whereNull('deleted_at'),
            ],
            'name'                  => ['required', 'string', 'max:120'],
            // {code} yer tutucusu içerebilir → katı url doğrulaması yapma.
            'tracking_url_template' => ['nullable', 'string', 'max:255'],
            'is_active'             => ['nullable', 'boolean'],
            'sort_order'            => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kod yalnızca büyük harf, rakam, tire ve alt çizgi içerebilir.',
        ];
    }
}
