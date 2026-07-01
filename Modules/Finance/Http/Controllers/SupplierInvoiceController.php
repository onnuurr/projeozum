<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\ProductionOrder;
use Modules\Finance\Http\Requests\StoreSupplierInvoiceRequest;
use Modules\Finance\Http\Requests\UpdateSupplierInvoiceRequest;
use Modules\Finance\Models\SupplierInvoice;
use Modules\Finance\Services\SupplierInvoiceService;

class SupplierInvoiceController extends Controller
{
    public function __construct(private readonly SupplierInvoiceService $service)
    {
    }

    public function index(): Response
    {
        $invoices = $this->service->list()->map(fn (SupplierInvoice $invoice) => [
            'id'                 => $invoice->id,
            'invoiceNo'          => $invoice->invoice_no,
            'supplierName'       => $invoice->supplier_name,
            'supplierTaxNumber'  => $invoice->supplier_tax_number,
            'invoiceDate'        => optional($invoice->invoice_date)->format('Y-m-d'),
            'dueDate'            => optional($invoice->due_date)->format('Y-m-d'),
            'currency'           => $invoice->currency,
            'subtotal'           => (float) $invoice->subtotal,
            'taxAmount'          => (float) $invoice->tax_amount,
            'total'              => (float) $invoice->total,
            'status'             => $invoice->status,
            'paidAt'             => optional($invoice->paid_at)->format('Y-m-d H:i'),
            'category'           => $invoice->category,
            'productionOrderId'  => $invoice->production_order_id,
            'productionOrderCode' => $invoice->productionOrder?->code,
            'note'               => $invoice->note,
        ]);

        $productionOrders = ProductionOrder::query()
            ->select(['id', 'code'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (ProductionOrder $order) => ['value' => $order->id, 'label' => $order->code]);

        return Inertia::render('Finance::SupplierInvoices', [
            'invoices'         => $invoices->values(),
            'productionOrders' => $productionOrders,
        ]);
    }

    public function store(StoreSupplierInvoiceRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->id);

        return redirect()->route('finance.supplier-invoices.index');
    }

    public function update(UpdateSupplierInvoiceRequest $request, SupplierInvoice $supplierInvoice): RedirectResponse
    {
        $this->service->update($supplierInvoice, $request->validated());

        return redirect()->route('finance.supplier-invoices.index');
    }

    public function destroy(SupplierInvoice $supplierInvoice): RedirectResponse
    {
        $this->service->delete($supplierInvoice);

        return redirect()->route('finance.supplier-invoices.index');
    }

    public function markPaid(SupplierInvoice $supplierInvoice): RedirectResponse
    {
        $this->service->markAsPaid($supplierInvoice);

        return redirect()->route('finance.supplier-invoices.index');
    }
}
