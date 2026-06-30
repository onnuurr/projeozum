<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Modules\Tenant\Services\TenantPurchaseAnalyticsService;

class PortalCreditController extends Controller
{
    public function __construct(private TenantPurchaseAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $ledger = TenantCreditLedger::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->paginate(30)
            ->through(fn (TenantCreditLedger $l) => [
                'id'            => $l->id,
                'type'          => $l->type,
                'amount'        => (float) $l->amount,
                'reason'        => $l->reason,
                'order_id'      => $l->order_id,
                'invoice_id'    => $l->invoice_id,
                'balance_after' => (float) $l->balance_after,
                'created_at'    => optional($l->created_at)->toIso8601String(),
            ]);

        return Inertia::render('Tenant::Portal/Credit', [
            'tenant'   => [
                'id'   => $tenant->id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'snapshot' => $this->analytics->creditSnapshot($tenant, 0),
            'ledger'   => $ledger,
        ]);
    }
}
