<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Services\ProductCostReportService;

class ProductCostReportController extends Controller
{
    public function __construct(private readonly ProductCostReportService $service)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Finance::ProductCosts', [
            'products' => $this->service->build()->values(),
        ]);
    }
}
