<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Superadmin\Services\SystemInfoService;

class SystemInfoController extends Controller
{
    public function __invoke(SystemInfoService $service): JsonResponse
    {
        return response()->json([
            'system'    => $service->payload(),
            'fetchedAt' => now()->toIso8601String(),
        ]);
    }
}
