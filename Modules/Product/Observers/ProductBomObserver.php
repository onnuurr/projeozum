<?php

namespace Modules\Product\Observers;

use Modules\Atelier\Models\ProductBom;
use Modules\Product\Jobs\GenerateProductSeoContentJob;

/**
 * Reçete (BOM) yaşam döngüsünü izler. Yeni bir reçete kaydedilince ürünün
 * SEO içeriğini (başlık + açıklamalar + meta) AI ile üretmek üzere kuyruğa alır.
 *
 * config('product.ai.auto_seo_on_bom') ile kapatılabilir (testlerde kapalı).
 */
class ProductBomObserver
{
    public function created(ProductBom $bom): void
    {
        if (! config('product.ai.auto_seo_on_bom', false)) {
            return;
        }

        if ($bom->product_id === null) {
            return;
        }

        GenerateProductSeoContentJob::dispatch((int) $bom->product_id);
    }
}
