<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePartnerBrandKitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Gerçek yetki kontrolü route middleware'inde (can:creative.partnerbrandkit.manage)
        // — BrandKitController/StoreBrandKitRequest ile aynı konvansiyon.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:191'],
            'is_active'               => ['sometimes', 'boolean'],
            'logo_path'               => ['nullable', 'string', 'max:500'],
            'contact'                 => ['nullable', 'array'],
            'contact.phone'           => ['nullable', 'string', 'max:64'],
            'contact.email'           => ['nullable', 'email', 'max:191'],
            'contact.website'         => ['nullable', 'string', 'max:255'],
            'cta_phrases'             => ['nullable', 'array'],
            'cta_phrases.*'           => ['string', 'max:191'],
            'price_display'           => ['nullable', 'array'],
            'price_display.enabled'   => ['sometimes', 'boolean'],
            'price_display.note'      => ['nullable', 'string', 'max:500'],
            'notes'                   => ['nullable', 'string', 'max:2000'],
        ];
    }
}
