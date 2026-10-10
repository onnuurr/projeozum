<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\ProductAttribute;

/**
 * Ürün Ayarları → Özellikler tanımını Bagisto attribute'u olarak gönderir (Webkul\SaasSync
 * AttributeWebhookController): kod anahtar; Bagisto attribute'u açar/günceller, ürün ailesine
 * (attribute_family_code) ekler ve admininde kilitler. Seçenekler ayrı job'la gider
 * (PushAttributeOptionToBagisto) — Bagisto'da attribute yoksa seçenek 422 alır ve retry'da düzelir.
 *
 * Silmede kayıt artık yok — kod constructor'da taşınır. SaaS, ürünlerde kullanılan özelliği
 * silmeye izin vermez (ProductAttributeAdminService).
 */
class PushProductAttributeToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const EVENT_UPSERTED = 'upserted';
    public const EVENT_DELETED = 'deleted';

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public function __construct(
        protected int $attributeId,
        protected string $code,
        protected string $event = self::EVENT_UPSERTED,
    ) {}

    public static function forAttribute(ProductAttribute $attribute, string $event = self::EVENT_UPSERTED): self
    {
        return new self($attribute->id, $attribute->code, $event);
    }

    public function handle(BagistoSyncClient $client): void
    {
        if ($this->event === self::EVENT_DELETED) {
            $client->post('api/saas-sync/attributes', [
                'event' => self::EVENT_DELETED,
                'code'  => $this->code,
            ]);

            return;
        }

        $attribute = ProductAttribute::find($this->attributeId);

        // Kuyrukta beklerken silinmiş: silme job'ı ayrıca gönderiliyor.
        if (! $attribute) {
            return;
        }

        $client->post('api/saas-sync/attributes', [
            'event'                 => self::EVENT_UPSERTED,
            'code'                  => $attribute->code,
            'name'                  => $attribute->name,
            'type'                  => $attribute->type,
            'is_filterable'         => $attribute->is_filterable,
            'position'              => (int) $attribute->sort_order,
            'attribute_family_code' => config('bagisto.sync.attribute_family_code'),
        ]);
    }
}
