<?php

namespace Modules\Product\Listeners;

use Illuminate\Support\Facades\Auth;
use Modules\Product\Events\Contracts\ProductDomainEvent;
use Modules\Product\Models\ProductTimelineEntry;

/**
 * Her `ProductDomainEvent`'i `product_timeline_events` tablosuna yazar (Faz 1).
 * Senkron çalışır — modülde queue kullanan başka bir bileşen yok, tek bir DB
 * insert için queue altyapısı gerekmiyor.
 */
class RecordProductTimelineEntry
{
    public function handle(ProductDomainEvent $event): void
    {
        ProductTimelineEntry::create([
            'product_id' => $event->product()->id,
            'type'       => $event->timelineType(),
            'payload'    => $event->timelinePayload(),
            'user_id'    => Auth::id(),
        ]);
    }
}
