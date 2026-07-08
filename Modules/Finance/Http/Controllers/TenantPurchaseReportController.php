<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Services\TenantPurchaseReportService;

class TenantPurchaseReportController extends Controller
{
    public function __construct(private readonly TenantPurchaseReportService $service)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Finance::TenantPurchases', [
            'summary'  => $this->service->summary(),
            'invoices' => $this->service->list()->values(),
        ]);
    }
}
