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

            // D6: kargo `cargo` (sabit ücret, eşik üstü ücretsiz) veya `pickup` (depodan teslim).
            'shipping_method'         => ['required', Rule::in(['cargo', 'pickup'])],

            // Her siparişte ZORUNLU: kargo firması + bayinin anlaşmalı kargo müşteri kodu.
            // Firma aktif ve silinmemiş olmalı.
            'carrier_id'              => [
                'required',
                'integer',
                Rule::exists('carriers', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'cargo_customer_code'     => ['required', 'string', 'max:64'],

            'note'                    => ['nullable', 'string', 'max:500'],
            'terms_accepted'          => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'carrier_id.required'          => 'Kargo firması seçmek zorunludur.',
            'carrier_id.exists'            => 'Seçilen kargo firması geçerli değil.',
            'cargo_customer_code.required' => 'Kargo müşteri kodu zorunludur.',
        ];
    }
}
