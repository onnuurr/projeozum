<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Bagisto\Mappers\ProductPayloadMapper;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\Product;

/**
 * SaaS'ta ürün oluşturma/güncelleme/silme sonrası Bagisto'ya push eder.
 * `Webkul\SaasSync\Jobs\PushEventToSaas` (Bagisto tarafı) ile parite: aynı
 * tries/backoff — checkout/ürün kayıt akışını bloklamadan kuyruk üzerinden.
 */
class PushProductToBagisto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    /**
     * @param  'created'|'updated'|'deleted'  $event
     */
    public function __construct(
        protected int $productId,
        protected string $event,
    ) {}

    public function handle(ProductPayloadMapper $mapper, BagistoSyncClient $client): void
    {
        $product = Product::withTrashed()
            ->with(['variants.images', 'images', 'category'])
            ->find($this->productId);

        if (! $product) {
            Log::warning('BagistoSync: ürün bulunamadı, push atlandı.', [
                'product_id' => $this->productId,
                'event'      => $this->event,
            ]);

            return;
        }

        $payload = $this->event === 'deleted'
            ? $mapper->toDeletePayload($product)
            : $mapper->toUpsertPayload($product, $this->event);

        $client->post('api/saas-sync/products', $payload);
    }
}
