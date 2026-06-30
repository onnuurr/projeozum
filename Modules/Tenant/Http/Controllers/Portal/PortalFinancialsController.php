<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantFinancialsService;

class PortalFinancialsController extends Controller
{
    public function __construct(private TenantFinancialsService $financials) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $to   = now();
        $from = now()->subDays(30);

        return Inertia::render('Tenant::Portal/Financials', [
            'tenant'        => ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'summary'       => $this->financials->summary($tenant, $from, $to),
            'byMarketplace' => $this->financials->byMarketplace($tenant, $from, $to),
            'byMonth'       => $this->financials->byMonth($tenant, 12),
        ]);
    }
}
