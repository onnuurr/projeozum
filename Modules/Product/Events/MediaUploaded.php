<?php

namespace Modules\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Product\Events\Contracts\ProductDomainEvent;
use Modules\Product\Models\Product;

class MediaUploaded implements ProductDomainEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Product $product,
        public readonly int $imageCount,
    ) {}

    public function product(): Product
    {
        return $this->product;
    }

    public function timelineType(): string
    {
        return 'product.media_uploaded';
    }

    public function timelinePayload(): array
    {
        return ['image_count' => $this->imageCount];
    }
}
