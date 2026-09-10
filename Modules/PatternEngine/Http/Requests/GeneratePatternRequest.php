<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePatternRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('patternengine.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            // Beden/giysi seti profile göre değişir (§5.2, Sprint 4) — sabit `in:` listesi
            // yok. Seçilen profilde gerçekten var mı kontrolü Python tarafında yapılır;
            // uyuşmazlık job'ı `failed` durumuna düşürüp açık bir hata mesajıyla döner (§P4).
            'garment'                        => ['required', 'string'],
            'size'                            => ['required', 'string'],
            // Doluysa gradasyonlu üretim istenir (§4.5, Sprint 3) — taban `size` dışındaki
            // ek bedenler. `size`'ın kendisi burada tekrarlanmamalı (queueGraded ayıklar).
            'grade_sizes'                     => ['nullable', 'array'],
            'grade_sizes.*'                   => ['string', 'different:size'],
            'profile'                         => ['nullable', 'string'],
            'design_params.neck_depth_front'  => ['nullable', 'numeric', 'min:0'],
            'design_params.sleeve_length'     => ['nullable', 'numeric', 'min:0'],
            'design_params.body_length'       => ['nullable', 'numeric', 'min:0'],
            'design_params.hem_flare'         => ['nullable', 'numeric'],
            'design_params.fit'               => ['nullable', 'string', 'in:slim,regular,loose'],
        ];
    }

    public function messages(): array
    {
        return [
            'garment.required'      => 'Giysi tipi zorunludur.',
            'size.required'         => 'Beden zorunludur.',
            'grade_sizes.*.different' => 'Ek bedenler taban bedenle aynı olamaz.',
            'design_params.fit.in'  => 'Uyum yalnızca slim, regular veya loose olabilir.',
        ];
    }
}
