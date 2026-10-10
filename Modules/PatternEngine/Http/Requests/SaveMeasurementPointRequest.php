<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PatternEngine\Models\PatternMeasurementPoint;

class SaveMeasurementPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.settings') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            // Anahtar PDF eşlemesinde (Burda lejantı → chest_circ) kullanılır: küçük harf + alt çizgi.
            'key'        => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('pattern_measurement_points', 'key')->ignore($this->route('point'))],
            'name'       => ['required', 'string', 'max:100'],
            'hint'       => ['nullable', 'string', 'max:191'],
            'basis'      => ['required', Rule::in(PatternMeasurementPoint::BASES)],
            'unit'       => ['nullable', 'string', 'max:8'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['boolean'],
        ];
    }
}
