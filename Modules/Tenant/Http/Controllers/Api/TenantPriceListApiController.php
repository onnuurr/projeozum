<?php

namespace Modules\Tenant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Http\Requests\StorePriceListRequest;
use Modules\Tenant\Http\Resources\TenantPriceListResource;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantPriceList;
use Modules\Tenant\Services\TenantService;

class TenantPriceListApiController extends Controller
{
    public function __construct(private TenantService $service) {}

    public function index(Tenant $tenant): JsonResponse
    {
        $priceLists = $this->service->getPriceListForTenant($tenant);

        return TenantPriceListResource::collection($priceLists)->response();
    }

    public function store(StorePriceListRequest $request, Tenant $tenant): JsonResponse
    {
        $priceList = $this->service->addPriceList($tenant, $request->validated());

        return (new TenantPriceListResource($priceList))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Tenant $tenant, TenantPriceList $priceList): JsonResponse
    {
        if ((int) $priceList->tenant_id !== (int) $tenant->id) {
            return response()->json(['message' => 'Fiyat listesi bu tenant\'a ait değil.'], 403);
        }

        $priceList->delete();

        return response()->json(null, 204);
    }
}
