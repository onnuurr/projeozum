<?php

namespace Modules\Tenant\Http\Controllers\Portal\Marketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Models\MarketplaceSale;
use Modules\Tenant\Models\MarketplaceSyncLog;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Services\Marketplace\MarketplaceSyncOrchestrator;

class TrendyolController extends Controller
{
    public function __construct(private MarketplaceSyncOrchestrator $orchestrator) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $recentLogs = MarketplaceSyncLog::query()
            ->where('tenant_id', $tenant->id)
            ->where('marketplace', 'trendyol')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'operation', 'status', 'items_processed', 'error_message', 'started_at', 'finished_at']);

        $salesCount = MarketplaceSale::query()
            ->where('tenant_id', $tenant->id)
            ->where('marketplace', 'trendyol')
            ->count();

        return Inertia::render('Tenant::Portal/Marketplace/Trendyol/Dashboard', [
            'tenant'      => $this->payload($tenant),
            'recentLogs'  => $recentLogs,
            'salesCount'  => $salesCount,
        ]);
    }

    public function pushProducts(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $data = $request->validate([
            'product_ids'   => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ]);

        $this->orchestrator->pushProducts($tenant->id, 'trendyol', $data['product_ids']);

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Push kuyruğa alındı', 'message' => count($data['product_ids']) . ' ürün'],
        ]);
    }

    public function pullOrders(Request $request): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        \Modules\Tenant\Jobs\Marketplace\Trendyol\PullTrendyolOrdersJob::dispatch(
            $tenant->id,
            now()->subDay()->toIso8601String(),
        );

        return back()->with('flash', [
            'toast' => ['type' => 'success', 'title' => 'Sipariş çekme kuyruğa alındı', 'message' => 'Son 24 saat'],
        ]);
    }

    public function listings(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $sales = MarketplaceSale::query()
            ->where('tenant_id', $tenant->id)
            ->where('marketplace', 'trendyol')
            ->orderByDesc('sold_at')
            ->paginate(30);

        return Inertia::render('Tenant::Portal/Marketplace/Trendyol/Listings', [
            'tenant' => $this->payload($tenant),
            'sales'  => $sales,
        ]);
    }

    public function categoryTree(Request $request): \Illuminate\Http\JsonResponse
    {
        // Trendyol kategori autocomplete — Phase 3 sonraki iterasyonda TrendyolCategoryClient
        // ile gerçek API'dan çekilir; stub mode'da boş döner.
        return response()->json([
            'data' => config('marketplace.driver') === 'stub'
                ? [['id' => 411, 'name' => 'Giyim > Üst Giyim > Bluz']]
                : [],
        ]);
    }

    private function payload(Tenant $t): array
    {
        return ['id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'slug' => $t->slug];
    }
}
