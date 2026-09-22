<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Services\TenantPurchaseReportService;
use Modules\Product\Models\Order;

class TenantPurchaseReportController extends Controller
{
    public function __construct(private readonly TenantPurchaseReportService $service)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Finance::TenantPurchases', [
            'summary'      => $this->service->summary(),
            'orders'       => $this->service->list()->values(),
            'byProduct'    => $this->service->byProduct()->values(),
            'statusLabels' => Order::statuses(),
        ]);
    }
}
