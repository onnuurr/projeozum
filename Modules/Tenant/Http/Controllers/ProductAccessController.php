<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;
use Modules\Tenant\Services\TenantAccessService;

/**
 * Ürün-merkezli tenant erişim yönetimi.
 *
 * Aynı tablo (tenant_product_access) ama UI ters yönden:
 * "bu ürün × tüm tenantlar" matrisi.
 */
class ProductAccessController extends Controller
{
    public function show(Product $product, TenantAccessService $access): Response
    {
        $product->load(['brand:id,name', 'category:id,name', 'variants:id,product_id,price']);

        $matrix = $access->productMatrix($product);

        return Inertia::render('Tenant::ProductTenantAccess', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'brand_name' => $product->brand?->name,
                'category_name' => $product->category?->name,
                'default_price' => $matrix['default_price'],
            ],
            'rows' => $matrix['rows'],
        ]);
    }

    public function upsert(Request $request, Product $product, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'is_blocked' => ['nullable', 'boolean'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        TenantProductAccess::updateOrCreate(
            [
                'product_id' => $product->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'is_blocked' => $data['is_blocked'] ?? false,
                'custom_price' => $data['custom_price'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
        );

        // Flash basılmıyor — ProductTenantAccess.vue onSuccess'te kendi toast'unu gösteriyor.
        return back();
    }

    public function destroy(Product $product, Tenant $tenant): RedirectResponse
    {
        TenantProductAccess::query()
            ->where('product_id', $product->id)
            ->where('tenant_id', $tenant->id)
            ->delete();

        return back();
    }
}
