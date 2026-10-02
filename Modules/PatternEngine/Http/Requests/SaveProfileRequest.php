<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('patternengine.profile.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Python tarafındaki slug deseniyle birebir aynı (bkz. spec.py validate_profile_name).
            'name'                            => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{0,63}$/', 'not_in:ts_default'],
            'base_profile'                    => ['nullable', 'string'],
            'sizes'                           => ['required', 'array', 'min:1'],
            'sizes.*'                         => ['array', 'min:1'],
            'sizes.*.*'                       => ['numeric'],
            'provenance.source'               => ['required', 'string', 'max:255'],
            'provenance.verified_by_modelist' => ['nullable', 'boolean'],
            'provenance.note'                 => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => __('patternengine::messages.validation.profile_name_required'),
            'name.regex'         => __('patternengine::messages.validation.profile_name_format'),
            'name.not_in'        => __('patternengine::messages.validation.profile_name_reserved'),
            'sizes.required'     => __('patternengine::messages.validation.sizes_required'),
            'sizes.min'          => __('patternengine::messages.validation.sizes_required'),
            'provenance.source.required' => __('patternengine::messages.validation.provenance_source_required'),
        ];
    }
}
