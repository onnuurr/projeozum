<?php

namespace Modules\Tenant\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('tenant.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'nullable|exists:orders,id',
            'amount'   => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'status'   => ['nullable', Rule::in(['pending', 'paid', 'cancelled'])],
            'due_date' => 'nullable|date',
            'note'     => 'nullable|string',
        ];
    }
}
