<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Product\Events\ProductUpdated;

class PushProductSync
{
    public function handle(ProductCreated|ProductUpdated|ProductDeleted|ProductStatusChanged $event): void
    {
        $syncEvent = match ($event::class) {
            ProductCreated::class       => 'created',
            ProductUpdated::class       => 'updated',
            ProductDeleted::class       => 'deleted',
            // Upsert payload'ı güncel status'u taşır (published → Bagisto'da aktif).
            ProductStatusChanged::class => 'updated',
        };

        PushProductToBagisto::dispatch($event->product()->id, $syncEvent);
    }
}
