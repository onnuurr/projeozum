<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'label'  => ['required', 'string', 'max:120'],
            'prompt' => ['required', 'string', 'max:500'],
        ];
    }
}
