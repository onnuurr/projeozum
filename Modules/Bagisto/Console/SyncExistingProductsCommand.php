<?php

namespace Modules\Bagisto\Console;

use Illuminate\Console\Command;
use Modules\Bagisto\Jobs\PushProductToBagisto;
use Modules\Product\Models\Product;

/**
 * Faz 1 entegrasyonu devreye alınmadan ÖNCE oluşturulmuş ürünler event-driven
 * senkrona hiç girmedi (ProductCreated sadece create() anında ateşlenir). Bu
 * komut mevcut katalogu tek seferlik Bagisto'ya push eder (backfill).
 *
 * Push kuyruk üzerinden yapılır (aynı PushProductToBagisto job'ı) — bu komut
 * sadece job'ları sıraya diziyor, gerçek HTTP çağrısını ozumserver-queue-worker yapar.
 */
class SyncExistingProductsCommand extends Command
{
    protected $signature = 'bagisto:sync-products';

    protected $description = 'Mevcut tüm ürünleri Bagisto\'ya push etmek için kuyruğa diziler (backfill).';

    public function handle(): int
    {
        $total = 0;

        Product::query()->chunkById(200, function ($products) use (&$total) {
            foreach ($products as $product) {
                PushProductToBagisto::dispatch($product->id, 'created');
                $total++;
            }
        });

        $this->info("{$total} ürün Bagisto senkron kuyruğuna dizildi.");

        return self::SUCCESS;
    }
}
