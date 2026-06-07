<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'svg'  => [
                'required',
                'file',
                'mimetypes:image/svg+xml,text/plain,text/xml,application/xml',
                'max:5120',
            ],
        ];
    }
}
