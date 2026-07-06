<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;

class MarketplaceHubController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $credentials = TenantMarketplaceCredential::query()
            ->where('tenant_id', $tenant->id)
            ->get(['marketplace', 'store_name', 'is_active', 'last_sync_at']);

        $providers = collect(TenantMarketplaceCredential::MARKETPLACES)
            ->map(function (string $code) use ($credentials) {
                $cred = $credentials->firstWhere('marketplace', $code);

                return [
                    'code'        => $code,
                    'label'       => TenantMarketplaceCredential::LABELS[$code] ?? $code,
                    'connected'   => $cred !== null,
                    'active'      => $cred?->is_active ?? false,
                    'store_name'  => $cred?->store_name,
                    'last_sync_at'=> optional($cred?->last_sync_at)->toIso8601String(),
                    // Trendyol live; diğerleri stub-only şu an.
                    'live_ready'  => $code === 'trendyol',
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Tenant::Portal/Marketplace/Index', [
            'tenant'    => ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'providers' => $providers,
        ]);
    }
}
