<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\PatternEngine\Models\PatternMeasurementPoint;

/** Kalıp Kartı › Ölçüler: tüm tablolar (vücut + hazır giysi) tek seferde. */
class SavePatternMeasurementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.manage') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'tables'                       => ['present', 'array'],
            'tables.*.basis'               => ['required', Rule::in(PatternMeasurementPoint::BASES)],
            'tables.*.group_label'         => ['nullable', 'string', 'max:50'],
            'tables.*.rows'                => ['array'],
            'tables.*.rows.*.point_id'     => ['nullable', 'integer', 'exists:pattern_measurement_points,id'],
            'tables.*.rows.*.custom_label' => ['nullable', 'string', 'max:100'],
            'tables.*.rows.*.values'       => ['array'],
            'tables.*.rows.*.values.*'     => ['nullable', 'numeric', 'between:0,99999'],
        ];
    }
}
