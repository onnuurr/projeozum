<?php

namespace Modules\Product\Services\Ai\Contracts;

use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\ProductDescriptionResult;
use Modules\Tenant\Models\Tenant;

interface ProductDescriptionGenerator
{
    /**
     * $tenant verilirse tenant-özel bir tenant_description üretilir.
     * Aksi halde iki farklı ton (public + tenant default) döner.
     */
    public function generate(Product $product, ?Tenant $tenant = null): ProductDescriptionResult;
}
