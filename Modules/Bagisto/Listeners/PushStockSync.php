<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Jobs\PushStockToBagisto;
use Modules\Product\Events\StockChanged;

class PushStockSync
{
    public function handle(StockChanged $event): void
    {
        PushStockToBagisto::dispatch($event->variant->id);
    }
}
