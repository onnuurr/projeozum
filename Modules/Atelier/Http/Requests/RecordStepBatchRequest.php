<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordStepBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.production.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'good_qty'  => ['required', 'integer', 'min:0'],
            'scrap_qty' => ['nullable', 'integer', 'min:0'],
            'note'      => ['nullable', 'string', 'max:1000'],
        ];
    }
}
