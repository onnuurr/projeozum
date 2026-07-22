<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'brand_kit_id'  => ['required', 'integer', 'exists:brand_kits,id'],
            'preset'        => ['required', 'string', Rule::in(array_keys((array) config('creative.template_presets', [])))],
            'formats'       => ['required', 'array', 'min:1'],
            'formats.*'     => ['string', Rule::in(array_keys((array) config('creative.formats', [])))],
        ];
    }
}
