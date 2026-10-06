<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder;

/** Creative ayarlar › Mokap › Pozlar: uygun bölgeler (boş = mokap dışı), ürün gereksinimi, kamera yönü. */
class UpdatePoseMockupTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('mockup-settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'mockup_regions'   => ['array'],
            'mockup_regions.*' => [Rule::in(MockupOutfit::REGIONS)],
            'requires'         => ['array'],
            'requires.*'       => [Rule::in(MockupPromptBuilder::PRODUCT_FEATURES)],
            'view'             => ['required', Rule::in(['front', 'side', 'back', 'three_quarter'])],
        ];
    }
}
