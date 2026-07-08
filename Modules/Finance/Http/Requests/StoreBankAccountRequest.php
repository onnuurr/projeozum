<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'bank_name'      => ['required', 'string', 'max:191'],
            'account_name'   => ['required', 'string', 'max:191'],
            'iban'           => ['required', 'string', 'max:34', 'unique:finance_bank_accounts,iban'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'is_active'      => ['boolean'],
            'note'           => ['nullable', 'string'],
        ];
    }
}
