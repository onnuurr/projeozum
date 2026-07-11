<?php

namespace Modules\Tenant\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Models\TenantMarketplaceCredential;
use Modules\Tenant\Services\ProfitCalculatorService;

class PortalProfitController extends Controller
{
    public function __construct(private ProfitCalculatorService $calculator) {}

    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return Inertia::render('Tenant::Portal/ProfitCalculator', [
            'tenant'       => ['id' => $tenant->id, 'code' => $tenant->code, 'name' => $tenant->name, 'slug' => $tenant->slug],
            'marketplaces' => collect(TenantMarketplaceCredential::MARKETPLACES)
                ->map(fn ($code) => ['code' => $code, 'label' => TenantMarketplaceCredential::LABELS[$code] ?? $code])
                ->values()
                ->all(),
        ]);
    }

    public function compute(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $data = $request->validate([
            'product_id'  => ['required', 'integer', 'exists:products,id'],
            'variant_id'  => ['nullable', 'integer', 'exists:product_variants,id'],
            'marketplace' => ['required', 'string', 'max:32'],
            'sell_price'  => ['required', 'numeric', 'min:0'],
            'qty'         => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = isset($data['variant_id']) ? ProductVariant::find($data['variant_id']) : null;

        $breakdown = $this->calculator->calculate(
            tenant: $tenant,
            product: $product,
            variant: $variant,
            marketplace: $data['marketplace'],
            sellPrice: (float) $data['sell_price'],
            qty: (int) ($data['qty'] ?? 1),
        );

        return response()->json(['data' => $breakdown->toArray()]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::query()
            ->accessibleToTenant($tenant->id)
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"))
            ->limit(15)
            ->get(['id', 'name', 'sku'])
            ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]);

        return response()->json(['products' => $products]);
    }
}
