<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Creative ayarlar › Mokap › Genel: otomatik tetik, günlük limit, mokapta kullanılacak mankenler (boş = onaylı hepsi). */
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
            'mannequins.*'     => ['integer', 'exists:creative_mannequins,id'],
        ];
    }
}
