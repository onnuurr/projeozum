<?php

namespace Modules\Tenant\Services;

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
     *   1) tenant_product_access.custom_price varsa → o
     *   2) Tenant tipi price_list_type ile eşleşen aktif price_list varsa → o
     *   3) Aksi halde variant.price (yoksa product.price)
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
            $list = \Modules\Product\Models\PriceList::query()
                ->where('product_variant_id', $variant->id)
                ->where('type', $priceListType)
                ->where('is_active', true)
                ->first();

            if ($list) {
                return (float) $list->price;
            }
        }

        return (float) ($variant?->price ?? $product->price);
    }
}
