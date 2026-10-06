<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Creative ayarlar › Mokap › Genel: otomatik tetik, günlük limit, varsayılan mankenler. */
class SaveMockupSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mockup-settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'auto_trigger'     => ['required', 'boolean'],
            'daily_limit'      => ['required', 'integer', 'min:0', 'max:500'],
            'mannequins'       => ['array'],
            'mannequins.girl'  => ['nullable', 'integer', 'exists:creative_mannequins,id'],
            'mannequins.boy'   => ['nullable', 'integer', 'exists:creative_mannequins,id'],
        ];
    }
}
