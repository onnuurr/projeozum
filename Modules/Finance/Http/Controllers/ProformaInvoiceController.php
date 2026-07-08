<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Http\Requests\StoreProformaInvoiceRequest;
use Modules\Finance\Http\Requests\UpdateProformaInvoiceRequest;
use Modules\Finance\Models\ProformaInvoice;
use Modules\Finance\Models\ProformaInvoiceItem;
use Modules\Finance\Services\ProformaInvoiceService;
use Modules\Product\Models\Order;

class ProformaInvoiceController extends Controller
{
    public function __construct(private readonly ProformaInvoiceService $service)
    {
    }

    public function index(): Response
    {
        $proformas = $this->service->list()->map(fn (ProformaInvoice $p) => [
            'id'                 => $p->id,
            'proformaNo'         => $p->proforma_no,
            'orderId'            => $p->order_id,
            'orderNo'            => $p->order?->order_no,
            'tenantInvoiceId'    => $p->tenant_invoice_id,
            'tenantName'         => $p->tenantInvoice?->tenant?->name,
            'buyerName'          => $p->buyer_name,
            'buyerTaxNumber'     => $p->buyer_tax_number,
            'issueDate'          => optional($p->issue_date)->format('Y-m-d'),
            'validUntil'         => optional($p->valid_until)->format('Y-m-d'),
            'currency'           => $p->currency,
            'subtotal'           => (float) $p->subtotal,
            'taxAmount'          => (float) $p->tax_amount,
            'total'              => (float) $p->total,
            'status'             => $p->status,
            'convertedInvoiceId' => $p->converted_invoice_id,
            'note'               => $p->note,
            'items'              => $p->items->map(fn (ProformaInvoiceItem $i) => [
                'id'          => $i->id,
                'description' => $i->description,
                'qty'         => $i->qty,
                'unitPrice'   => (float) $i->unit_price,
                'totalPrice'  => (float) $i->total_price,
            ]),
        ]);

        $orders = Order::query()
            ->select(['id', 'order_no'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Order $order) => ['value' => $order->id, 'label' => $order->order_no]);

        return Inertia::render('Finance::ProformaInvoices', [
            'proformas' => $proformas->values(),
            'orders'    => $orders,
        ]);
    }

    public function store(StoreProformaInvoiceRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $items = $request->validated('items', []);

        $this->service->create($data, $items, $request->user()->id);

        return redirect()->route('finance.proformas.index');
    }

    public function update(UpdateProformaInvoiceRequest $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $data = $request->safe()->except('items');
        $items = $request->validated('items', []);

        $this->service->update($proformaInvoice, $data, $items);

        return redirect()->route('finance.proformas.index');
    }

    public function destroy(ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $this->service->delete($proformaInvoice);

        return redirect()->route('finance.proformas.index');
    }

    public function convert(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        $this->service->convertToOutgoingInvoice($proformaInvoice, $request->user()->id);

        return redirect()->route('finance.proformas.index');
    }
}
