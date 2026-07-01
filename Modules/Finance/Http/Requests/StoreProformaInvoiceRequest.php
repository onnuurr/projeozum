<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProformaInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'proforma_no'         => ['required', 'string', 'max:64'],
            'order_id'            => ['nullable', Rule::exists('orders', 'id')],
            'tenant_invoice_id'   => ['nullable', Rule::exists('tenant_invoices', 'id')],
            'buyer_name'          => ['required', 'string', 'max:191'],
            'buyer_tax_number'    => ['nullable', 'string', 'max:32'],
            'issue_date'          => ['required', 'date'],
            'valid_until'         => ['required', 'date', 'after_or_equal:issue_date'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'subtotal'            => ['required', 'numeric', 'min:0'],
            'tax_amount'          => ['required', 'numeric', 'min:0'],
            'total'               => ['required', 'numeric', 'min:0'],
            'note'                => ['nullable', 'string'],

            // Yalnızca order/tenant_invoice'a bağlı olmayan (standalone) proformalar için kalemler.
            'items'                    => ['required_without_all:order_id,tenant_invoice_id', 'array'],
            'items.*.description'     => ['required_with:items', 'string', 'max:191'],
            'items.*.qty'             => ['required_with:items', 'integer', 'min:1'],
            'items.*.unit_price'      => ['required_with:items', 'numeric', 'min:0'],
            'items.*.total_price'     => ['required_with:items', 'numeric', 'min:0'],
        ];
    }
}
