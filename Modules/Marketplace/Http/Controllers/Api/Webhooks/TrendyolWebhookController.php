<?php

namespace Modules\Marketplace\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Marketplace\Jobs\Trendyol\ProcessTrendyolWebhookJob;
use Modules\Marketplace\Models\TenantMarketplaceCredential;
use Modules\Marketplace\Services\MarketplaceServiceResolver;

class TrendyolWebhookController extends Controller
{
    public function __construct(private MarketplaceServiceResolver $resolver) {}

    public function handle(Request $request): Response
    {
        // Trendyol supplier_id'yi header veya body'de gönderir; tenant'ı bunun üzerinden bul.
        $supplierId = $request->header('X-Trendyol-Supplier-Id')
            ?? ($request->input('supplierId') ?? $request->input('order.supplierId'));

        if (! $supplierId) {
            return response('supplier_id eksik', 400);
        }

        $cred = TenantMarketplaceCredential::query()
            ->where('marketplace', 'trendyol')
            ->where('supplier_id', $supplierId)
            ->where('is_active', true)
            ->first();

        if (! $cred) {
            return response('Tanınmayan supplier', 404);
        }

        $service = $this->resolver->for($cred->tenant, 'trendyol');
        if (! $service->verifyWebhook($request)) {
            return response('İmza doğrulanamadı', 401);
        }

        ProcessTrendyolWebhookJob::dispatch($cred->tenant_id, $request->all());

        return response('', 202);
    }
}
