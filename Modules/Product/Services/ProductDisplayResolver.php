<?php

namespace Modules\Product\Services;

use Modules\Product\Models\Product;
use Modules\Product\Services\DTOs\ProductDisplayDto;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantProductAccess;

/**
 * Portal/katalog için ürünün gösterilecek adını ve açıklamasını çözer.
 *
 * Fallback zinciri:
 *   name        : tenant_override.custom_name → products.public_name → products.name
 *   description : tenant_override.custom_description → products.tenant_description → products.public_description → products.care_instructions
 *
 * Kaynak (`source`) tenant override kullanıldıysa 'tenant_override', aksi halde 'default'.
 */
class ProductDisplayResolver
{
    public function for(Product $product, ?Tenant $tenant = null): ProductDisplayDto
    {
        $override = $tenant
            ? TenantProductAccess::query()
                ->where('tenant_id', $tenant->id)
                ->where('product_id', $product->id)
                ->first()
            : null;

        $usedOverride = false;

        $name = null;
        if ($override && filled($override->custom_name)) {
            $name = $override->custom_name;
            $usedOverride = true;
        } elseif (filled($product->public_name)) {
            $name = $product->public_name;
        } else {
            $name = $product->name;
        }

        $description = null;
        if ($override && filled($override->custom_description)) {
            $description = $override->custom_description;
            $usedOverride = true;
        } elseif (filled($product->tenant_description)) {
            $description = $product->tenant_description;
        } elseif (filled($product->public_description)) {
            $description = $product->public_description;
        } elseif (filled($product->care_instructions)) {
            $description = $product->care_instructions;
        }

        return new ProductDisplayDto(
            name: $name,
            description: $description,
            source: $usedOverride ? ProductDisplayDto::SOURCE_TENANT_OVERRIDE : ProductDisplayDto::SOURCE_DEFAULT,
        );
    }
}
