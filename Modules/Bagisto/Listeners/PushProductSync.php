<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductImagesChanged;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Models\Product;

class PushProductSync
{
    public function handle(ProductCreated|ProductUpdated|ProductDeleted|ProductStatusChanged|ProductImagesChanged $event): void
    {
        // Üretimi bitmemiş geçici kart (Atölye "Üretilecek Ürün") mağazaya hiç gitmez; ilk push
        // üretim tamamlanıp development → draft geçişinde (ProductStatusChanged) yapılır.
        if ($this->isDevelopmentCard($event)) {
            return;
        }

        // Görsel değişikliği yalnız ürün id'si taşır (model olayından gelir) — tam upsert gönderilir.
        if ($event instanceof ProductImagesChanged) {
            PushProductToBagisto::dispatch($event->productId, 'updated');

            return;
        }

        $syncEvent = match ($event::class) {
            ProductCreated::class       => 'created',
            ProductUpdated::class       => 'updated',
            ProductDeleted::class       => 'deleted',
            // Upsert payload'ı güncel status'u taşır (published → Bagisto'da aktif).
            // Geçici karttan dönüşümde ürün mağazada henüz yok — ilk push 'created'.
            ProductStatusChanged::class => $event->from === Product::STATUS_DEVELOPMENT ? 'created' : 'updated',
        };

        PushProductToBagisto::dispatch($event->product()->id, $syncEvent);
    }

    private function isDevelopmentCard(ProductCreated|ProductUpdated|ProductDeleted|ProductStatusChanged|ProductImagesChanged $event): bool
    {
        // Mağazaya hiç gitmemiş geçici kartın arşivlenmesi de gönderilmez.
        if ($event instanceof ProductStatusChanged) {
            return $event->from === Product::STATUS_DEVELOPMENT && $event->to === Product::STATUS_ARCHIVED;
        }

        $status = $event instanceof ProductImagesChanged
            ? Product::withTrashed()->whereKey($event->productId)->value('status')
            : $event->product()->status;

        return $status === Product::STATUS_DEVELOPMENT;
    }
}
