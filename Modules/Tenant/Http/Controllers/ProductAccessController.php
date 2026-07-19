<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAccessRule;
use Modules\Tenant\Models\TenantProductAccess;

/**
 * Ürün-merkezli tenant erişim yönetimi.
 *
 * Aynı tablo (tenant_product_access) ama UI ters yönden:
 * "bu ürün × tüm tenantlar" matrisi.
 */
class ProductAccessController extends Controller
{
    public function show(Product $product): Response
    {
        $product->load(['brand:id,name', 'category:id,name', 'variants:id,product_id,price']);

        $tenants = Tenant::query()
            ->with('type:id,name,code,price_list_type')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Pivot override'ları tek seferde
        $overrideMap = TenantProductAccess::query()
            ->where('product_id', $product->id)
            ->get()
            ->keyBy('tenant_id');

        // Bu ürünün brand/category'sini bloklayan kural varsa hangi tenantlar etkili — tek sorgu
        $blockedRuleTenantIds = TenantAccessRule::query()
            ->where('is_blocked', true)
            ->where(function ($q) use ($product) {
                $q->where(function ($x) use ($product) {
                    $x->where('scope_type', TenantAccessRule::SCOPE_BRAND)
                        ->where('scope_id', $product->brand_id);
                })->orWhere(function ($x) use ($product) {
                    $x->where('scope_type', TenantAccessRule::SCOPE_CATEGORY)
                        ->where('scope_id', $product->category_id);
                });
            })
            ->pluck('tenant_id')
            ->unique()
            ->all();

        $blockedByRule = array_flip($blockedRuleTenantIds);

        // Bu ürünün varyantları için tüm aktif price_lists — type'a göre sonradan filtrele
        $variantIds = $product->variants->pluck('id')->all();
        $priceListRows = PriceList::query()
            ->whereIn('product_variant_id', $variantIds)
            ->where('is_active', true)
            ->get(['product_variant_id', 'type', 'price']);

        // Her tenant tipine göre o tipin minimum varyant fiyatı (tip referans fiyatı)
        $minPriceByType = [];
        foreach ($priceListRows->groupBy('type') as $type => $rows) {
            $minPriceByType[$type] = (float) $rows->min('price');
        }

        $defaultMinPrice = $product->variants->isEmpty()
            ? (float) $product->price
            : (float) $product->variants->min('price');

        $tenantRows = $tenants->map(function (Tenant $t) use ($overrideMap, $blockedByRule, $minPriceByType, $defaultMinPrice) {
            $override = $overrideMap[$t->id] ?? null;
            $ruleBlocked = isset($blockedByRule[$t->id]);

            // Etkin durum hesabı (resolve)
            // 1) pivot override is_blocked=true → gizli
            // 2) pivot override is_blocked=false → açık (kural override edilir)
            // 3) pivot yok: kural varsa gizli, yoksa açık
            if ($override) {
                $effective = $override->is_blocked ? 'hidden' : 'allowed';
                $source = $override->is_blocked ? 'override_block' : 'override_allow';
            } elseif ($ruleBlocked) {
                $effective = 'hidden';
                $source = 'rule_block';
            } else {
                $effective = 'allowed';
                $source = 'default';
            }

            $typePrice = $t->type?->price_list_type
                ? ($minPriceByType[$t->type->price_list_type] ?? null)
                : null;

            return [
                'tenant_id'      => $t->id,
                'tenant_code'    => $t->code,
                'tenant_name'    => $t->name,
                'tenant_type'    => $t->type?->name,
                'tenant_type_id' => $t->type?->id,
                'price_list_type'=> $t->type?->price_list_type,
                'override_id'    => $override?->id,
                'override_is_blocked' => $override?->is_blocked,
                'custom_price'   => $override?->custom_price !== null ? (float) $override->custom_price : null,
                'notes'          => $override?->notes,
                'rule_blocked'   => $ruleBlocked,
                'effective'      => $effective,
                'source'         => $source,
                'type_price'     => $typePrice,
                'default_price'  => $defaultMinPrice,
            ];
        });

        return Inertia::render('Tenant::ProductTenantAccess', [
            'product' => [
                'id'            => $product->id,
                'name'          => $product->name,
                'slug'          => $product->slug,
                'sku'           => $product->sku,
                'brand_name'    => $product->brand?->name,
                'category_name' => $product->category?->name,
                'default_price' => $defaultMinPrice,
            ],
            'rows' => $tenantRows,
        ]);
    }

    public function upsert(Request $request, Product $product, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'is_blocked'   => ['nullable', 'boolean'],
            'custom_price' => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        TenantProductAccess::updateOrCreate(
            [
                'product_id' => $product->id,
                'tenant_id'  => $tenant->id,
            ],
            [
                'is_blocked'   => $data['is_blocked'] ?? false,
                'custom_price' => $data['custom_price'] ?? null,
                'notes'        => $data['notes'] ?? null,
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
