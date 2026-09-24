<?php

namespace Modules\Product\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Bir ürünün görsel kümesi değişti (ekleme, kapak/sıra değişikliği, silme, geri alma).
 * `ProductImage` model olaylarından fırlatılır — hangi modül yazarsa yazsın
 * (ProductImageController, Creative "Kapak yap"/try-on onayı…) kapsanır.
 * Transaction içindeyse commit'ten SONRA dağıtılır.
 *
 * `ProductService` kendi create/update akışında görselleri sessizce yazar (bkz.
 * `ProductImage::withoutChangeAnnouncements()`), çünkü o akış zaten
 * ProductCreated/ProductUpdated fırlatıyor. Timeline dinlemez; dışarıdan (ör. Bagisto
 * senkronu) dinlenmesi için var.
 */
class ProductImagesChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly int $productId) {}
}
