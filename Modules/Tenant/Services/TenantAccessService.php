<?php

namespace Modules\Tenant\Services;

use Illuminate\Support\Collection;
use Modules\Product\Models\PriceList;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantAccessRule;
use Modules\Tenant\Models\TenantProductAccess;

class TenantAccessService
{
    /**
     * Tenant verilen ürüne erişebiliyor mu?
     *
     * Karar sırası:
     *   1) tenant_product_access pivot kaydı varsa → onun is_blocked'unun TERSİ döner
     *   2) tenant_access_rules içinde ürünün brand/category'sini bloklayan kural varsa → false
     *   3) Aksi halde true (blacklist mode = varsayılan açık)
     */
    public function canAccess(Tenant $tenant, Product $product): bool
    {
        $override = TenantProductAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $product->id)
            ->first();

        if ($override) {
            return ! $override->is_blocked;
        }

        $blockedByRule = TenantAccessRule::query()
            ->where('tenant_id', $tenant->id)
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
            ->exists();

        return ! $blockedByRule;
    }

    /**
     * Tenant için bir ürün/varyantın gösterilecek fiyatı.
     *
     * Karar sırası:
     *   1) tenant_product_access.custom_price varsa → o (net fiyat, iskonto uygulanmaz)
     *   2) Tenant tipi price_list_type ile eşleşen aktif price_list varsa → o (net fiyat)
     *   3) Aksi halde variant.price (yoksa product.price) − tenant.discount_rate %
     *
     * SaaS'taki ürün fiyatı satış fiyatıdır; bayi iskontosu onun üzerinden uygulanır.
     * Bagisto aynı oranı müşteri grubu fiyatı olarak gösterir (bkz. TenantPayloadMapper
     * `discount_rate`, Bagisto SaasSync DealerDiscountGroups) — 1) ve 2) Bagisto'ya
     * yansımaz, kullanılırsa mağazada görünen fiyatla çekilen fiyat ayrışır.
     */
    public function priceFor(Tenant $tenant, Product $product, ?ProductVariant $variant = null): float
    {
        $override = TenantProductAccess::query()
            ->where('tenant_id', $tenant->id)
            ->where('product_id', $product->id)
            ->whereNotNull('custom_price')
            ->first();

        if ($override) {
            return (float) $override->custom_price;
        }

        $priceListType = $tenant->type?->price_list_type;

        if ($priceListType && $variant) {
            $list = PriceList::query()
                ->where('product_variant_id', $variant->id)
                ->where('type', $priceListType)
                ->where('is_active', true)
                ->first();

            if ($list) {
                return (float) $list->price;
            }
        }

        return $this->applyDiscount($tenant, (float) ($variant?->price ?? $product->price));
    }

    /** Bayi iskontosunu satış fiyatına uygular (Bagisto'nun customer group "discount" hesabıyla aynı). */
    public function applyDiscount(Tenant $tenant, float $price): float
    {
        $rate = min(max((float) $tenant->discount_rate, 0), 100);

        return round($price - $price * $rate / 100, 2);
    }

    /**
     * Ürün-merkezli erişim matrisi: "bu ürün × tüm aktif tenantlar".
     * canAccess()/priceFor() ile aynı karar sırasını N+1 olmadan, toplu sorgularla
     * uygular (bkz. ProductAccessController::show).
     *
     * @return array{default_price: float, rows: Collection<int, array>}
     */
    public function productMatrix(Product $product): array
    {
        $product->loadMissing('variants:id,product_id,price');

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

        $blockedByRule = array_flip($this->ruleBlockedTenantIds($product));
        $minPriceByType = $this->minPriceByListType($product);
        $defaultMinPrice = $product->variants->isEmpty()
            ? (float) $product->price
            : (float) $product->variants->min('price');

        $rows = $tenants->map(fn (Tenant $t) => $this->matrixRow(
            $t,
            $overrideMap[$t->id] ?? null,
            isset($blockedByRule[$t->id]),
            $minPriceByType,
            $defaultMinPrice,
        ));

        return ['default_price' => $defaultMinPrice, 'rows' => $rows];
    }

    /** Bu ürünün brand/category'sini bloklayan kuralı olan tenant id'leri — tek sorgu. */
    private function ruleBlockedTenantIds(Product $product): array
    {
        return TenantAccessRule::query()
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
    }

    /** Her price_list tipi için ürün varyantlarının minimum aktif fiyatı (tip referans fiyatı). */
    private function minPriceByListType(Product $product): array
    {
        $rows = PriceList::query()
            ->whereIn('product_variant_id', $product->variants->pluck('id')->all())
            ->where('is_active', true)
            ->get(['product_variant_id', 'type', 'price']);

        $minPriceByType = [];
        foreach ($rows->groupBy('type') as $type => $group) {
            $minPriceByType[$type] = (float) $group->min('price');
        }

        return $minPriceByType;
    }

    private function matrixRow(
        Tenant $t,
        ?TenantProductAccess $override,
        bool $ruleBlocked,
        array $minPriceByType,
        float $defaultMinPrice,
    ): array {
        [$effective, $source] = $this->resolveEffective($override, $ruleBlocked);

        $priceListType = $t->type?->price_list_type;

        return [
            'tenant_id' => $t->id,
            'tenant_code' => $t->code,
            'tenant_name' => $t->name,
            'tenant_type' => $t->type?->name,
            'tenant_type_id' => $t->type?->id,
            'price_list_type' => $priceListType,
            'override_id' => $override?->id,
            'override_is_blocked' => $override?->is_blocked,
            'custom_price' => $override?->custom_price !== null ? (float) $override->custom_price : null,
            'notes' => $override?->notes,
            'rule_blocked' => $ruleBlocked,
            'effective' => $effective,
            'source' => $source,
            'type_price' => $priceListType ? ($minPriceByType[$priceListType] ?? null) : null,
            'default_price' => $defaultMinPrice,
        ];
    }

    /**
     * Etkin durum (canAccess() ile aynı sıra):
     *   1) pivot override is_blocked=true → gizli
     *   2) pivot override is_blocked=false → açık (kural override edilir)
     *   3) pivot yok: kural varsa gizli, yoksa açık
     *
     * @return array{0: string, 1: string} [effective, source]
     */
    private function resolveEffective(?TenantProductAccess $override, bool $ruleBlocked): array
    {
        if ($override) {
            return $override->is_blocked
                ? ['hidden', 'override_block']
                : ['allowed', 'override_allow'];
        }

        return $ruleBlocked ? ['hidden', 'rule_block'] : ['allowed', 'default'];
    }
}
