<?php

namespace Modules\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Product\Models\Product;

/**
 * Her yaşam döngüsü geçişinde (`ProductService::transitionStatus()`, commit'ten sonra)
 * fırlatılır — `ProductPublished`/`ProductArchived`'ın aksine taslağa dönüş
 * (published → draft) dahil TÜM geçişleri kapsar. Timeline bunu dinlemez (yayın/arşiv
 * kendi event'leriyle zaten kaydediliyor); dışarıdan (ör. Bagisto senkronu: mağazada
 * ürünü açıp kapatmak) dinlenmesi için var.
 */
class ProductStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly string $from,
        public readonly string $to,
    ) {}

    public function product(): Product
    {
        return $this->product;
    }
}
