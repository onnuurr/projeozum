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
            'name.required'      => 'Profil adı zorunludur.',
            'name.regex'         => 'Profil adı yalnızca küçük harf, rakam, "_" ve "-" içerebilir (ilk karakter harf/rakam).',
            'name.not_in'        => "'ts_default' referans profildir, bu adla kaydedilemez.",
            'sizes.required'     => 'En az bir beden girilmelidir.',
            'sizes.min'          => 'En az bir beden girilmelidir.',
            'provenance.source.required' => 'Ölçü tablosunun kaynağı zorunludur.',
        ];
    }
}
