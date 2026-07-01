<?php

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Finance\Models\OutgoingInvoice;

class UpdateOutgoingInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('finance.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_no'        => ['required', 'string', 'max:64'],
            'invoice_type'      => ['required', Rule::in([
                OutgoingInvoice::TYPE_SALES_ORDER,
                OutgoingInvoice::TYPE_TENANT_SALE,
                OutgoingInvoice::TYPE_STANDALONE,
            ])],
            'order_id'          => ['nullable', Rule::exists('orders', 'id')],
            'tenant_invoice_id' => ['nullable', Rule::exists('tenant_invoices', 'id')],
            'buyer_name'        => ['required', 'string', 'max:191'],
            'buyer_tax_number'  => ['nullable', 'string', 'max:32'],
            'buyer_address'     => ['nullable', 'string', 'max:191'],
            'issue_date'        => ['required', 'date'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'subtotal'          => ['required', 'numeric', 'min:0'],
            'tax_amount'        => ['required', 'numeric', 'min:0'],
            'total'             => ['required', 'numeric', 'min:0'],
            'status'            => ['required', Rule::in([
                OutgoingInvoice::STATUS_DRAFT,
                OutgoingInvoice::STATUS_READY,
                OutgoingInvoice::STATUS_SENT,
                OutgoingInvoice::STATUS_ACCEPTED,
                OutgoingInvoice::STATUS_REJECTED,
                OutgoingInvoice::STATUS_CANCELLED,
            ])],
            'note'              => ['nullable', 'string'],
        ];
    }
}
