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
            'image.required' => __('patternengine::messages.validation.image_required'),
            'image.image'    => __('patternengine::messages.validation.image_type'),
            'image.mimes'    => __('patternengine::messages.validation.image_mimes'),
            'image.max'      => __('patternengine::messages.validation.image_max'),
            'garment.required' => __('patternengine::messages.validation.garment_required'),
            'size.required'     => __('patternengine::messages.validation.size_required'),
        ];
    }
}
