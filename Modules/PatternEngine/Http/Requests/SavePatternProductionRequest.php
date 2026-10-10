<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Kalıp Kartı › Genel / Parçalar / Kumaş & Malzeme. */
class SavePatternProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.manage') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'pattern_type_id'     => ['nullable', 'integer', 'exists:pattern_types,id'],
            'size_range'          => ['nullable', 'string', 'max:100'],
            'sizes'               => ['array'],
            'sizes.*'             => ['string', 'max:32', 'distinct'],
            'variants'            => ['array'],
            'variants.*.code'     => ['required', 'string', 'max:8'],
            'variants.*.name'     => ['nullable', 'string', 'max:100'],
            'seam_allowance_cm'   => ['nullable', 'numeric', 'between:0,99'],
            'hem_allowance_cm'    => ['nullable', 'numeric', 'between:0,99'],

            'parts'               => ['array'],
            'parts.*.part_no'     => ['nullable', 'string', 'max:16'],
            'parts.*.part_name'   => ['nullable', 'string', 'max:100'],
            'parts.*.quantity'    => ['nullable', 'integer', 'min:1', 'max:999'],
            'parts.*.size_range'  => ['nullable', 'string', 'max:100'],
            'parts.*.variants'    => ['nullable', 'string', 'max:50'],
            'parts.*.fabric_code' => ['nullable', 'string', 'max:16'],
            'parts.*.notes'       => ['nullable', 'string', 'max:191'],

            'fabric_usage'                   => ['nullable', 'array'],
            'fabric_usage.fabrics'           => ['array'],
            'fabric_usage.fabrics.*.code'    => ['required', 'string', 'max:16'],
            'fabric_usage.fabrics.*.name'    => ['nullable', 'string', 'max:100'],
            'fabric_usage.rows'              => ['array'],
            'fabric_usage.rows.*.variant'    => ['nullable', 'string', 'max:8'],
            'fabric_usage.rows.*.fabric'     => ['nullable', 'string', 'max:16'],
            'fabric_usage.rows.*.width_cm'   => ['nullable', 'numeric', 'between:1,999'],
            'fabric_usage.rows.*.values'     => ['array'],
            'fabric_usage.rows.*.values.*'   => ['nullable', 'numeric', 'between:0,999'],

            'materials'              => ['array'],
            'materials.*.name'       => ['required', 'string', 'max:100'],
            'materials.*.quantity'   => ['nullable', 'numeric', 'between:0,99999'],
            'materials.*.unit'       => ['nullable', 'string', 'max:16'],
            'materials.*.variants'   => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string,mixed> patterns kolonları */
    public function patternData(): array
    {
        $v = $this->validated();
        $fabric = $v['fabric_usage'] ?? null;
        if ($fabric !== null) {
            $fabric = ['unit' => 'm', 'fabrics' => array_values($fabric['fabrics'] ?? []), 'rows' => array_values($fabric['rows'] ?? [])];
            if ($fabric['fabrics'] === [] && $fabric['rows'] === []) {
                $fabric = null;
            }
        }

        return [
            'pattern_type_id'   => $v['pattern_type_id'] ?? null,
            'size_range'        => $v['size_range'] ?? null,
            'sizes'             => array_values($v['sizes'] ?? []) ?: null,
            'variants'          => array_values($v['variants'] ?? []) ?: null,
            'seam_allowance_cm' => $v['seam_allowance_cm'] ?? null,
            'hem_allowance_cm'  => $v['hem_allowance_cm'] ?? null,
            'fabric_usage'      => $fabric,
            'materials'         => array_values($v['materials'] ?? []) ?: null,
        ];
    }
}
