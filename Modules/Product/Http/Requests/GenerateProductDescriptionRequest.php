<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateProductDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('product.ai.generate') ?? false;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['nullable', 'integer', Rule::exists('tenants', 'id')],
        ];
    }
}
