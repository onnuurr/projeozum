<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Creative\Services\ProductCloseupService;

class GenerateCloseupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creative.asset.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Kadraj anahtarları: config('creative.closeup.shots')
            'shots'   => ['required', 'array', 'min:1'],
            'shots.*' => ['string', Rule::in(array_keys(ProductCloseupService::shotOptions()))],
        ];
    }
}
