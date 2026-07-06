<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDropshipOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('portal.checkout') ?? false;
    }

    public function rules(): array
    {
        return [
            // billing_to: 'us' → fatura bayiye kesilir (tenant adresi), 'customer' → son müşteriye.
            'billing_to'              => ['required', Rule::in(['us', 'customer'])],

            'address'                 => ['required', 'array'],
            'address.name'            => ['required', 'string', 'max:120'],
            'address.phone'           => ['required', 'string', 'max:32'],
            'address.street'          => ['required', 'string', 'max:500'],
            'address.district'        => ['nullable', 'string', 'max:80'],
            'address.city'            => ['required', 'string', 'max:80'],
            'address.postal_code'     => ['nullable', 'string', 'max:16'],

            'shipping_method'         => ['required', Rule::in(['standard', 'express', 'same_day'])],
            'note'                    => ['nullable', 'string', 'max:500'],
            'promo_code'              => ['nullable', 'string', 'max:32'],
            'terms_accepted'          => ['accepted'],

            // Dropship için ödeme yöntemi anlamsız (bize borç olarak yazılır) — yine de log için kabul.
            'payment_method'          => ['nullable', 'string', 'max:32'],
        ];
    }
}
