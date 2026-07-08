<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Services\BankAccountService;
use Modules\Finance\Services\BankImport\ReconciliationService;
use Modules\Finance\Services\OutgoingInvoiceService;
use Modules\Finance\Services\ProductCostReportService;
use Modules\Finance\Services\ProformaInvoiceService;
use Modules\Finance\Services\SalesHistoryReportService;
use Modules\Finance\Services\SupplierInvoiceService;
use Modules\Finance\Services\TenantPurchaseReportService;

class DashboardController extends Controller
{
    public function __construct(
        private readonly SalesHistoryReportService $salesReport,
        private readonly TenantPurchaseReportService $tenantPurchaseReport,
        private readonly ProductCostReportService $costReport,
        private readonly SupplierInvoiceService $supplierInvoiceService,
        private readonly ProformaInvoiceService $proformaInvoiceService,
        private readonly OutgoingInvoiceService $outgoingInvoiceService,
        private readonly BankAccountService $bankAccountService,
        private readonly ReconciliationService $reconciliationService,
    ) {
    }

    public function index(): Response
    {
        $costs = $this->costReport->build();

        return Inertia::render('Finance::Dashboard', [
            'sales'            => $this->salesReport->summary(),
            'tenantPurchases'  => $this->tenantPurchaseReport->summary(),
            'costs'            => [
                'productCount'           => $costs->count(),
                'avgMarginRate'          => round((float) $costs->whereNotNull('marginRate')->avg('marginRate'), 1),
                'productsFromProduction' => $costs->where('source', 'production')->count(),
            ],
            'supplierInvoices' => $this->supplierInvoiceService->summary(),
            'proformas'        => $this->proformaInvoiceService->summary(),
            'outgoingInvoices' => $this->outgoingInvoiceService->summary(),
            'bank'             => [
                'accountCount'   => $this->bankAccountService->accountCount(),
                'unmatchedCount' => $this->reconciliationService->unmatchedCount(),
            ],
        ]);
    }
}
