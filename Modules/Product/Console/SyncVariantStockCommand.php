<?php

namespace Modules\Product\Console;

use Illuminate\Console\Command;
use Modules\Product\Models\ProductVariant;
use Modules\Product\Models\Stock;

/**
 * product_variants.stock denormalize cache'ini stocks tablosunun toplamından yeniden kurar.
 *
 * Geçmiş sepet operasyonları (D1 öncesi) bu cache'i bozmuş olabilir. Deploy sonrası bir kez
 * çalıştırılır; kalıcı bir ops onarım aracıdır (veri onarımı migration'a KONMAZ).
 */
class SyncVariantStockCommand extends Command
{
    protected $signature = 'product:sync-variant-stock';

    protected $description = 'product_variants.stock cache\'ini stocks toplamından resync eder.';

    public function handle(): int
    {
        $sums = Stock::query()
            ->selectRaw('product_variant_id, SUM(quantity) as total')
            ->groupBy('product_variant_id')
            ->pluck('total', 'product_variant_id');

        $fixed = 0;

        ProductVariant::query()->chunkById(500, function ($variants) use (&$fixed, $sums) {
            foreach ($variants as $variant) {
                $total = (int) ($sums[$variant->id] ?? 0);
                if ((int) $variant->stock !== $total) {
                    $variant->update(['stock' => $total]);
                    $fixed++;
                }
            }
        });

        $this->info("Varyant stok cache'i güncellendi. Düzeltilen kayıt: {$fixed}.");

        return self::SUCCESS;
    }
}
