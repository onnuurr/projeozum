<?php

namespace Modules\Tenant\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantSettingsService;

class TenantSettingsApiController extends Controller
{
    public function __construct(private TenantSettingsService $service) {}

    public function show(Tenant $tenant): JsonResponse
    {
        return response()->json([
            'data' => $this->service->get($tenant),
        ]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $settings = $this->service->update($tenant, $request->all());

        return response()->json([
            'data'    => $settings,
            'message' => 'Tenant ayarları güncellendi.',
        ]);
    }
}
