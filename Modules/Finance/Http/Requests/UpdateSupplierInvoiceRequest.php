<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\SupplierInvoice;

class UpdateSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_no'           => ['required', 'string', 'max:64'],
            'supplier_name'        => ['required', 'string', 'max:191'],
            'supplier_tax_number'  => ['nullable', 'string', 'max:32'],
            'invoice_date'         => ['required', 'date'],
            'due_date'             => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'currency'             => ['nullable', 'string', 'size:3'],
            'subtotal'             => ['required', 'numeric', 'min:0'],
            'tax_amount'           => ['required', 'numeric', 'min:0'],
            'total'                => ['required', 'numeric', 'min:0'],
            'status'               => ['required', Rule::in([
                SupplierInvoice::STATUS_UNPAID,
                SupplierInvoice::STATUS_PARTIALLY_PAID,
                SupplierInvoice::STATUS_PAID,
                SupplierInvoice::STATUS_CANCELLED,
            ])],
            'category'             => ['nullable', 'string', 'max:100'],
            'production_order_id'  => ['nullable', Rule::exists('production_orders', 'id')],
            'note'                 => ['nullable', 'string'],
        ];
    }
}
