<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Creative ayarlar › Mokap › Stilist seçenekleri: toplu sil (eski kombinler anahtarla kalır). */
class DeleteStylistOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mockup-settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:creative_stylist_options,id'],
        ];
    }
}
