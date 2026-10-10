<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePatternTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.settings') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100', Rule::unique('pattern_types', 'name')->ignore($this->route('type'))],
            'is_active'   => ['boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
            'point_ids'   => ['array'],
            'point_ids.*' => ['integer', 'exists:pattern_measurement_points,id'],
        ];
    }
}
