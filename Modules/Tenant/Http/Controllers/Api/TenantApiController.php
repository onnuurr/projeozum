<?php

namespace Modules\Tenant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Tenant\Http\Requests\StoreTenantRequest;
use Modules\Tenant\Http\Requests\UpdateTenantRequest;
use Modules\Tenant\Http\Resources\TenantCollection;
use Modules\Tenant\Http\Resources\TenantResource;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantService;

class TenantApiController extends Controller
{
    public function __construct(private TenantService $service) {}

    public function index(): TenantCollection
    {
        $tenants = Tenant::with('type', 'users')->get();

        return new TenantCollection($tenants);
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->service->create($request->validated());

        return (new TenantResource($tenant->load('type')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Tenant $tenant): TenantResource
    {
        return new TenantResource($tenant->load('type', 'users'));
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $tenant = $this->service->update($tenant, $request->validated());

        return new TenantResource($tenant->load('type'));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        if ($tenant->users()->count() > 0) {
            return response()->json([
                'message' => 'Bu tenant\'a bağlı kullanıcılar var. Önce kullanıcıları kaldırın.',
            ], 422);
        }

        $tenant->delete();

        return response()->json(null, 204);
    }

    public function suspend(Tenant $tenant): TenantResource
    {
        $this->service->suspend($tenant);

        return new TenantResource($tenant->fresh()->load('type'));
    }

    public function activate(Tenant $tenant): TenantResource
    {
        $this->service->activate($tenant);

        return new TenantResource($tenant->fresh()->load('type'));
    }
}
