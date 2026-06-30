<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Services\SystemInfoService;

class SuperadminController extends Controller
{
    /**
     * Süper admin ana paneli (dashboard).
     *
     * Başlangıç render'ı hızlı kalsın diye PowerShell çağrıları yapan canlı
     * payload yerine staticPayload() kullanılır; canlı metrikleri Dashboard.vue
     * /superadmin/system-info uç noktasını polling ederek günceller.
     */
    public function index(SystemInfoService $service): Response
    {
        return Inertia::render('Superadmin::Dashboard', [
            'system' => $service->staticPayload(),
        ]);
    }

    public function create() {}

    public function store(Request $request) {}

    public function show($id) {}

    public function edit($id) {}

    public function update(Request $request, $id) {}

    public function destroy($id) {}
}
