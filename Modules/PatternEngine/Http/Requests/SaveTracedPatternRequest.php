<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class SaveTracedPatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.pattern.manage') ?? false;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name'                            => ['required', 'string', 'max:191'],
            'product_type'                    => ['nullable', 'string', 'max:100'],
            'size_range'                      => ['nullable', 'string', 'max:100'],
            'calibration.px_per_mm'           => ['required', 'numeric', 'gt:0'],
            'calibration.image_height_px'     => ['required', 'integer', 'gt:0'],
            'pieces'                          => ['required', 'array', 'min:1'],
            'pieces.*.name'                   => ['required', 'string', 'max:100'],
            'pieces.*.quantity'               => ['nullable', 'integer', 'min:1'],
            'pieces.*.size'                   => ['nullable', 'string', 'max:100'],
            'pieces.*.polylines'              => ['required', 'array', 'min:1'],
            'pieces.*.polylines.*.role'       => ['required', 'string', 'in:cut,grain'],
            'pieces.*.polylines.*.points'     => ['required', 'array', 'min:2'],
            'pieces.*.polylines.*.points.*'   => ['array', 'size:2'],
            'pieces.*.polylines.*.points.*.*' => ['numeric'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasClosedCut = false;
            foreach ((array) ($v->getData()['pieces'] ?? []) as $piece) {
                foreach ((array) ($piece['polylines'] ?? []) as $pl) {
                    if (($pl['role'] ?? null) === 'cut' && count($pl['points'] ?? []) >= 3) {
                        $hasClosedCut = true;
                    }
                }
            }
            if (! $hasClosedCut) {
                $v->errors()->add('pieces', 'En az bir kesim konturu (cut) ≥3 nokta içermeli.');
            }
        });
    }
}
