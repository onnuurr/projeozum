<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\TenantPurchaseAnalyticsService;

class PortalDashboardController extends Controller
{
    public function __construct(private TenantPurchaseAnalyticsService $analytics) {}

    public function __invoke(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $recentInvoices = $tenant->invoices()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'amount', 'currency', 'status', 'due_date', 'created_at'])
            ->map(fn ($i) => [
                'id'         => $i->id,
                'amount'     => (float) $i->amount,
                'currency'   => $i->currency,
                'status'     => $i->status,
                'due_date'   => optional($i->due_date)->toDateString(),
                'created_at' => optional($i->created_at)->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Tenant::Portal/Dashboard', [
            'tenant' => [
                'id'   => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'snapshot'       => $this->analytics->creditSnapshot($tenant),
            'topProducts'    => $this->analytics->topProducts($tenant, months: 6, limit: 10),
            'monthlyTrend'   => $this->analytics->monthlyTrend($tenant, months: 12),
            'recentInvoices' => $recentInvoices,
        ]);
    }
}
