<?php

namespace Modules\Product\Services\Ai\Contracts;

use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\ProductDescriptionPromptBuilder;
use Modules\Product\Services\Ai\ProductDescriptionResult;
use Modules\Tenant\Models\Tenant;

interface ProductDescriptionGenerator
{
    /**
     * $tenant verilirse tenant-özel bir tenant_description üretilir.
     * Aksi halde iki farklı ton (public + tenant default) döner.
     *
     * $product kaydedilmemiş bir taslak olabilir (ProductContentDraft); o durumda materyal satırları
     * formdan çözülüp $materialLines ile verilir. $parts: ProductDescriptionPromptBuilder::PARTS alt kümesi.
     *
     * @param  list<string>  $parts
     */
    public function generate(
        Product $product,
        ?Tenant $tenant = null,
        array $parts = [ProductDescriptionPromptBuilder::PART_DESCRIPTIONS],
        ?Collection $materialLines = null,
    ): ProductDescriptionResult;
}
