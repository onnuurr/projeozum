<?php

namespace Modules\Bagisto\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Bagisto\Services\BagistoSyncClient;
use Modules\Product\Models\ProductVariant;

/**
 * Bir varyantın toplam stoğu (tüm depolar, `StockService::move()` tarafından
 * denormalize edilen `product_variants.stock`) değiştiğinde Bagisto'ya push
 * eder. Bagisto tek bir `inventory_source_id` kullandığı için (MVP), SaaS'taki
 * çoklu-depo dağılımı buraya yansımaz — sadece toplam.
 *
 * `ShouldBeUnique`: aynı varyant için art arda dispatch'ler tek job'a
 * sıkıştırılır; `handle()` her zaman DB'den GÜNCEL stoğu okuduğu için hangi
 * kopyanın çalıştığı önemli değil, sadece sırayı bozan gereksiz tekrarları
 * engeller.
 */
class PushStockToBagisto implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 300, 900];

    public int $uniqueFor = 1400;

    public function __construct(protected int $variantId) {}

    public function uniqueId(): string
    {
        return (string) $this->variantId;
    }

    public function handle(BagistoSyncClient $client): void
    {
        $variant = ProductVariant::find($this->variantId);

        if (! $variant) {
            Log::warning('BagistoSync: varyant bulunamadı, stok push atlandı.', [
                'variant_id' => $this->variantId,
            ]);

            return;
        }

        $client->post('api/saas-sync/stock', [
            'sku'      => $variant->sku,
            'quantity' => (int) $variant->stock,
        ]);
    }
}
