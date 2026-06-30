<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMannequinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:120'],
            'gender'    => ['nullable', 'string', 'max:20'],
            'age_range' => ['nullable', 'string', 'max:30'],
            'skin_tone' => ['nullable', 'string', 'max:30'],
            'body_type' => ['nullable', 'string', 'max:30'],
            'hair'      => ['nullable', 'string', 'max:60'],
            'face'      => ['nullable', 'string', 'max:500'],
            'height_cm' => ['nullable', 'integer', 'min:30', 'max:260'],
            'bust_cm'   => ['nullable', 'integer', 'min:20', 'max:200'],
            'waist_cm'  => ['nullable', 'integer', 'min:20', 'max:200'],
            'hips_cm'   => ['nullable', 'integer', 'min:20', 'max:200'],
            'extras'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
