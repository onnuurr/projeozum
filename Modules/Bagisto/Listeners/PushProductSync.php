<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductUpdated;

class PushProductSync
{
    public function handle(ProductCreated|ProductUpdated|ProductDeleted $event): void
    {
        $syncEvent = match ($event::class) {
            ProductCreated::class => 'created',
            ProductUpdated::class => 'updated',
            ProductDeleted::class => 'deleted',
        };

        PushProductToBagisto::dispatch($event->product()->id, $syncEvent);
    }
}
