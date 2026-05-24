<?php

namespace Modules\Tenant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Http\Requests\StoreInvoiceRequest;
use Modules\Tenant\Http\Resources\TenantInvoiceResource;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Services\TenantService;

class TenantInvoiceApiController extends Controller
{
    public function __construct(private TenantService $service) {}

    public function index(Tenant $tenant): JsonResponse
    {
        $invoices = $tenant->invoices()->orderByDesc('created_at')->get();

        return TenantInvoiceResource::collection($invoices)->response();
    }

    public function store(StoreInvoiceRequest $request, Tenant $tenant): JsonResponse
    {
        $invoice = $this->service->createInvoice($tenant, $request->validated());

        return (new TenantInvoiceResource($invoice))
            ->response()
            ->setStatusCode(201);
    }

    public function markPaid(Tenant $tenant, TenantInvoice $invoice): JsonResponse
    {
        if ((int) $invoice->tenant_id !== (int) $tenant->id) {
            return response()->json(['message' => 'Fatura bu tenant\'a ait değil.'], 403);
        }

        $this->service->markInvoicePaid($invoice);

        return (new TenantInvoiceResource($invoice->fresh()))->response();
    }
}
