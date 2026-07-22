<?php

namespace Modules\Product\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Product\Events\Contracts\ProductDomainEvent;
use Modules\Product\Models\Product;

class ProductUpdated implements ProductDomainEvent
{
    use Dispatchable;

    public function __construct(public readonly Product $product) {}

    public function product(): Product
    {
        return $this->product;
    }

    public function timelineType(): string
    {
        return 'product.updated';
    }

    public function timelinePayload(): array
    {
        return [];
    }
}
