<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;

class PortalInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $invoices = $tenant->invoices()
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (TenantInvoice $i) => [
                'id'         => $i->id,
                'order_id'   => $i->order_id,
                'amount'     => (float) $i->amount,
                'currency'   => $i->currency,
                'status'     => $i->status,
                'due_date'   => optional($i->due_date)->toDateString(),
                'paid_at'    => optional($i->paid_at)->toIso8601String(),
                'created_at' => optional($i->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Tenant::Portal/Invoices', [
            'tenant'   => $this->tenantPayload($tenant),
            'invoices' => $invoices,
        ]);
    }

    public function show(Request $request, TenantInvoice $invoice): Response
    {
        $this->authorize('view', $invoice);

        return Inertia::render('Tenant::Portal/InvoiceDetail', [
            'tenant'  => $this->tenantPayload($request->attributes->get('tenant')),
            'invoice' => $this->invoicePayload($invoice),
        ]);
    }

    public function payload(Request $request, TenantInvoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'data' => [
                'tenant'  => [
                    'name'       => $tenant->name,
                    'code'       => $tenant->code,
                    'legal_name' => $tenant->legal_name,
                    'tax_number' => $tenant->tax_number,
                    'tax_office' => $tenant->tax_office,
                    'address'    => $tenant->address,
                    'city'       => $tenant->city,
                ],
                'invoice' => $this->invoicePayload($invoice),
            ],
        ]);
    }

    private function tenantPayload(Tenant $t): array
    {
        return [
            'id'   => $t->id,
            'code' => $t->code,
            'name' => $t->name,
            'slug' => $t->slug,
        ];
    }

    private function invoicePayload(TenantInvoice $i): array
    {
        return [
            'id'         => $i->id,
            'order_id'   => $i->order_id,
            'amount'     => (float) $i->amount,
            'currency'   => $i->currency,
            'status'     => $i->status,
            'due_date'   => optional($i->due_date)->toDateString(),
            'paid_at'    => optional($i->paid_at)->toIso8601String(),
            'note'       => $i->note,
            'created_at' => optional($i->created_at)->toIso8601String(),
        ];
    }
}
