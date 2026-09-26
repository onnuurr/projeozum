<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Finance\Exports\TenantSalesReportExport;
use Modules\Finance\Services\TenantPurchaseReportService;
use Modules\Product\Models\Order;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Finans › Bayi Satış Raporu: bayilerin Bagisto bayi portalından verdiği siparişler
 * (tarih/bayi/ürün/durum/kanal filtreli, bayi-kanal-ürün kırılımları, Excel çıktısı).
 */
class TenantPurchaseReportController extends Controller
{
    /** Sipariş listesi ekranda bu kadarla sınırlı; tamamı Excel'de. */
    private const LIST_LIMIT = 200;

    public function __construct(private readonly TenantPurchaseReportService $service)
    {
    }

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);
        $orders = $this->service->list($filters);

        return Inertia::render('Finance::TenantPurchases', [
            'summary'       => $this->service->summary($filters),
            'byTenant'      => $this->service->byTenant($filters),
            'byChannel'     => $this->service->byChannel($filters),
            'byProduct'     => $this->service->byProduct($filters)->take(50)->values(),
            'orders'        => $orders->take(self::LIST_LIMIT)->values(),
            'orderTotal'    => $orders->count(),
            'filters'       => $filters,
            'tenants'       => $this->service->tenantOptions(),
            'statusLabels'  => Order::statuses(),
            'channelLabels' => TenantPurchaseReportService::channelLabels(),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $this->filters($request);

        return Excel::download(
            new TenantSalesReportExport($this->service, $filters),
            'bayi-satis-raporu-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    private function filters(Request $request): array
    {
        return array_filter($request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'tenant_id' => ['nullable', 'integer'],
            'status'    => ['nullable', 'string', 'in:'.implode(',', array_keys(Order::statuses()))],
            'product'   => ['nullable', 'string', 'max:100'],
            'channel'   => ['nullable', 'string', 'in:'.implode(',', array_keys(TenantPurchaseReportService::channelLabels()))],
        ]), fn ($v) => $v !== null && $v !== '');
    }
}
