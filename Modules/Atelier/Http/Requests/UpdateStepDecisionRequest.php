<?php

namespace Modules\Atelier\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStepDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('atelier.production.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'location_type'     => ['required', Rule::in(['in_house', 'fason'])],
            'fason_supplier_id' => ['nullable', 'required_if:location_type,fason', 'integer', Rule::exists('fason_suppliers', 'id')],
            'unit_cost'         => ['nullable', 'numeric', 'min:0'],
            'note'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['fason_supplier_id.required_if' => 'Fason aşama için fasoncu seçilmeli.'];
    }
}
