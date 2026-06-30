<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;

class PortalDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Tenant::Portal/Dashboard', [
            'tenant' => [
                'id'   => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            // Phase 1 dolduracak (top products, monthly trend, credit snapshot).
            'snapshot' => [
                'credit_limit'      => (float) $tenant->credit_limit,
                'current_balance'   => (float) $tenant->current_balance,
                'available_credit'  => (float) $tenant->credit_limit - (float) $tenant->current_balance,
            ],
        ]);
    }
}
