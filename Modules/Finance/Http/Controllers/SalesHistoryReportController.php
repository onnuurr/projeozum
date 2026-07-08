<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Services\SalesHistoryReportService;

class SalesHistoryReportController extends Controller
{
    public function __construct(private readonly SalesHistoryReportService $service)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Finance::Sales', [
            'summary' => $this->service->summary(),
            'orders'  => $this->service->list()->values(),
        ]);
    }
}
