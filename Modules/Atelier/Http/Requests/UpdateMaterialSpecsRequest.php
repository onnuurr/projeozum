<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Atelier\Services\MaterialSpecService;

class UpdateMaterialSpecsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.material.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'specs'                     => ['nullable', 'array'],
            'specs.composition'         => ['nullable', 'string', 'max:255'],
            'specs.gsm'                 => ['nullable', 'integer', 'min:1', 'max:10000'],
            'specs.width_cm'            => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'specs.weave'               => ['nullable', 'string', 'max:64'],
            'specs.finish'              => ['nullable', 'string', 'max:255'],
            'specs.care_instructions'   => ['nullable', 'string', 'max:1000'],
            'specs.fiber_origin'        => ['nullable', 'string', 'max:64'],
            'specs.notes'               => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function specs(): array
    {
        $validated = $this->validated();
        $raw = (array) ($validated['specs'] ?? []);
        return array_intersect_key($raw, array_flip(MaterialSpecService::ALLOWED_KEYS));
    }
}
