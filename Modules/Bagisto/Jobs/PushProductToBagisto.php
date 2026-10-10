<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
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
 *
 * `ShouldBeUniqueUntilProcessing`: aynı ürün+event için kuyrukta BEKLEYEN
 * dispatch'ler tek job'a sıkıştırılır; kilit job işlenmeye başladığında kalkar.
 * (Eskiden `ShouldBeUnique` idi: kilit job BİTENE kadar tutulduğu için, çalışan
 * bir job durumu okuduktan sonra gelen değişikliğin — ör. arşivleme — dispatch'i
 * düşüyor ve mağazaya hiç yansımıyordu.) `handle()` her zaman DB'den GÜNCEL
 * durumu okuduğu için en son çalışan kopya en güncel durumu gönderir.
 */
class PushProductToBagisto implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    /**
     * Kilidin en kötü senaryoda (job hiç işlenmeye başlamadan kuyrukta kalırsa) ne kadar
     * asılı kalacağı — tüm backoff basamaklarının toplamı + tampon.
     */
    public int $uniqueFor = 1400;

    /**
     * @param  'created'|'updated'|'deleted'  $event
     */
    public function __construct(
        protected int $productId,
        protected string $event,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->productId}-{$this->event}";
    }

    public function handle(ProductPayloadMapper $mapper, BagistoSyncClient $client): void
    {
        // Kapak başta: Product::images() ilişkisi sort_order'la sabit sıralıdır ve
        // orderByDesc('is_cover')'ı ezer — reorder() şart (bkz. ProductController/Presenter).
        $coverFirst = fn ($q) => $q->reorder()->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id');

        $product = Product::withTrashed()
            ->with(['variants.images' => $coverFirst, 'images' => $coverFirst, 'category', 'brand'])
            ->find($this->productId);

        if (! $product) {
            Log::warning('BagistoSync: ürün bulunamadı, push atlandı.', [
                'product_id' => $this->productId,
                'event'      => $this->event,
            ]);

            return;
        }

        // Hiç yayınlanmamış ürün (yeni Taslak, geçici kart) mağazaya gitmez — silinmesi de; ilk yayında
        // ProductStatusChanged tam upsert gönderir. İş çalışırken bakılır: kuyrukta beklerken yayınlanmış olabilir.
        if (! $product->hasBeenPublished()) {
            return;
        }

        $payload = $this->event === 'deleted'
            ? $mapper->toDeletePayload($product)
            : $mapper->toUpsertPayload($product, $this->event);

        $client->post('api/saas-sync/products', $payload);
    }
}
