<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Finance\Models\BankStatementImport;

class ImportBankStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', Rule::exists('finance_bank_accounts', 'id')],
            'format'          => ['required', Rule::in([
                BankStatementImport::FORMAT_MT940,
                BankStatementImport::FORMAT_CSV,
                BankStatementImport::FORMAT_XLSX,
            ])],
            // .sta MT940 dosyaları için ayrık MIME eşlemesi olmadığından uzantı bazlı doğrulama tercih edildi.
            'file' => ['required', File::default()->max(10 * 1024)->extensions(['txt', 'sta', 'csv', 'xlsx', 'xls'])],
        ];
    }
}
