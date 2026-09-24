<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\HomeDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Giriş sonrası ana sayfa (/dashboard): modüller arası özet. Rota yalnızca `auth`+`verified`;
 * bölüm bazlı yetki kontrolü servis içinde (yetkisi olmayan bölüm null döner).
 */
class HomeDashboardController extends Controller
{
    public function __construct(private HomeDashboardService $dashboard) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'summary'     => $this->dashboard->build($request->user()),
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
