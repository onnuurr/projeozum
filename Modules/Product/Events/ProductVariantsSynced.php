<?php

namespace Modules\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Product\Events\Contracts\ProductDomainEvent;
use Modules\Product\Models\Product;

/**
 * `ProductService::syncVariants()` sil-ve-yeniden-oluştur yaptığı için tekil
 * VariantCreated/VariantUpdated yerine tek, dürüst bir "senkronlandı" event'i.
 */
class ProductVariantsSynced implements ProductDomainEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly int $variantCount,
    ) {}

    public function product(): Product
    {
        return $this->product;
    }

    public function timelineType(): string
    {
        return 'product.variants_synced';
    }

    public function timelinePayload(): array
    {
        return ['variant_count' => $this->variantCount];
    }
}
