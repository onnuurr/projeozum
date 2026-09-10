<?php

namespace Modules\PatternEngine\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DigitizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('patternengine.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'image'   => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:5120'],
            'garment' => ['required', 'string'],
            'size'    => ['required', 'string'],
            'profile' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'Bir teknik çizim/görsel seçilmelidir.',
            'image.image'    => 'Yalnızca görsel dosyaları kabul edilir.',
            'image.mimes'    => 'Görsel PNG veya JPEG formatında olmalıdır.',
            'image.max'      => 'Görsel en fazla 5 MB olabilir.',
            'garment.required' => 'Giysi tipi zorunludur.',
            'size.required'     => 'Beden zorunludur.',
        ];
    }
}
