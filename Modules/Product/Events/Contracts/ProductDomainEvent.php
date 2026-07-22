<?php

namespace Modules\Product\Events\Contracts;

use Modules\Product\Models\Product;

/**
 * Product Timeline'a yazılabilen tüm domain event'lerin ortak sözleşmesi (Faz 1).
 *
 * `RecordProductTimelineEntry` listener'ı bu arayüze göre çalışır; yeni bir
 * event eklemek istersen bunu implement etmen yeterli, listener veya migration
 * değişmez.
 */
interface ProductDomainEvent
{
    public function product(): Product;

    /**
     * Timeline satırının `type` kolonu, örn. 'product.created'.
     */
    public function timelineType(): string;

    /**
     * Timeline satırının `payload` (json) kolonuna yazılacak küçük bağlam.
     *
     * @return array<string, mixed>
     */
    public function timelinePayload(): array;
}
