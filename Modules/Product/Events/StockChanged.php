<?php

namespace Modules\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Product\Models\ProductVariant;

/**
 * Bir varyantın toplam stoğu (`StockService::move()` sonrası denormalize
 * `product_variants.stock`) değiştiğinde fırlatılır. Ürün Timeline'ıyla
 * ilgisi olmadığı için `ProductDomainEvent` sözleşmesini uygulamaz —
 * `Modules\Product\Providers\EventServiceProvider` bunu dinlemez, sadece
 * dışarıdan (ör. Bagisto senkron modülü) dinlenmesi için var.
 */
class StockChanged
{
    use Dispatchable;

    public function __construct(public readonly ProductVariant $variant) {}
}
