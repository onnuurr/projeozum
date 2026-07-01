<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Http\Requests\StoreOutgoingInvoiceRequest;
use Modules\Finance\Http\Requests\UpdateOutgoingInvoiceRequest;
use Modules\Finance\Models\OutgoingInvoice;
use Modules\Finance\Services\OutgoingInvoiceService;

class OutgoingInvoiceController extends Controller
{
    public function __construct(private readonly OutgoingInvoiceService $service)
    {
    }

    public function index(): Response
    {
        $invoices = $this->service->list()->map(fn (OutgoingInvoice $invoice) => [
            'id'                    => $invoice->id,
            'invoiceNo'             => $invoice->invoice_no,
            'invoiceType'           => $invoice->invoice_type,
            'orderNo'               => $invoice->order?->order_no,
            'tenantName'            => $invoice->tenantInvoice?->tenant?->name,
            'buyerName'             => $invoice->buyer_name,
            'buyerTaxNumber'        => $invoice->buyer_tax_number,
            'buyerAddress'          => $invoice->buyer_address,
            'issueDate'             => optional($invoice->issue_date)->format('Y-m-d'),
            'currency'              => $invoice->currency,
            'subtotal'              => (float) $invoice->subtotal,
            'taxAmount'             => (float) $invoice->tax_amount,
            'total'                 => (float) $invoice->total,
            'status'                => $invoice->status,
            'efaturaStatus'         => $invoice->efatura_status,
            'sentAt'                => optional($invoice->sent_at)->format('Y-m-d H:i'),
            'convertedFromProforma' => $invoice->convertedFromProforma?->proforma_no,
            'note'                  => $invoice->note,
        ]);

        return Inertia::render('Finance::OutgoingInvoices', [
            'invoices' => $invoices->values(),
        ]);
    }

    public function store(StoreOutgoingInvoiceRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->id);

        return redirect()->route('finance.outgoing-invoices.index');
    }

    public function update(UpdateOutgoingInvoiceRequest $request, OutgoingInvoice $outgoingInvoice): RedirectResponse
    {
        $this->service->update($outgoingInvoice, $request->validated());

        return redirect()->route('finance.outgoing-invoices.index');
    }

    public function destroy(OutgoingInvoice $outgoingInvoice): RedirectResponse
    {
        $this->service->delete($outgoingInvoice);

        return redirect()->route('finance.outgoing-invoices.index');
    }

    public function send(OutgoingInvoice $outgoingInvoice): RedirectResponse
    {
        $this->service->send($outgoingInvoice);

        return redirect()->route('finance.outgoing-invoices.index');
    }
}
