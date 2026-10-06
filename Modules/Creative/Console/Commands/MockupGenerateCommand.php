<?php

namespace Modules\Creative\Console\Commands;

use Illuminate\Console\Command;
use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Product\Models\Product;

/**
 * Bir ürün için mokap giydirmeyi uçtan uca başlatır (Faz 1 — arayüz Faz 4'te).
 * Sonuçlar Ürün Giydirme sayfasındaki onay kuyruğuna düşer; hiçbir şey onaysız yayınlanmaz.
 *
 *   php artisan creative:mockup 10 --mannequin=4
 *   php artisan creative:mockup 10 --mannequin=4 --garment=creative/tryon_garments/x.webp --sync
 *   php artisan creative:mockup 10 --mannequin=4 --now      # Batch API yerine anlık (2× fiyat)
 *
 * Varsayılan (config creative.mockup.batch): görsel çağrıları Gemini Batch API ile gider — %50 ucuz,
 * sonuçlar dakikalar–24 saat içinde gelir ve zamanlayıcı (creative:ai-batch) akışı sürdürür.
 */
class MockupGenerateCommand extends Command
{
    protected $signature = 'creative:mockup
        {product : Ürün id}
        {--mannequin= : Manken id (hazır + onaylı)}
        {--region= : Bölge tipini zorla: bottom|top|one_piece|set (varsayılan: kategoriden)}
        {--length= : Boyu zorla: mini|knee|midi|maxi (varsayılan: "Boy" özelliği / kategori / AI)}
        {--garment= : Ürünün yalın fotoğrafı yoksa giysi görseli (medya diskinde relative yol)}
        {--sync : Kombin + baz üretimini bu süreçte çalıştır (giydirmeler yine kuyruğa gider)}
        {--now : Batch API yerine anlık çağrı (sonuç hemen, fiyat 2×)}
        {--yes : Maliyet onayı sorma}';

    protected $description = 'Mokap giydirme: ürüne özel kombin + kilitli baz görseller + maske + birleştirme';

    public function handle(MockupPipelineService $pipeline): int
    {
        $product = Product::find($this->argument('product'));
        $mannequin = Mannequin::find($this->option('mannequin'));
        if (! $product || ! $mannequin) {
            $this->error('Ürün veya manken bulunamadı (--mannequin zorunlu).');

            return self::FAILURE;
        }
        foreach (['region' => MockupOutfit::REGIONS, 'length' => MockupOutfit::LENGTHS] as $opt => $allowed) {
            if ($this->option($opt) !== null && ! in_array($this->option($opt), $allowed, true)) {
                $this->error("--{$opt} şunlardan biri olmalı: " . implode(', ', $allowed));

                return self::FAILURE;
            }
        }

        // Ücretli AI çağrısından önce tahmini maliyet + onay (yeniden denemeler hariç).
        $batch = ! $this->option('now') && (bool) config('creative.mockup.batch', true);
        $est = $pipeline->estimate($batch);
        $this->line(sprintf(
            'Tahmini maliyet: %d görsel (%s, baz %s / giydirme %s, %s) + %d analiz ≈ %.2f $. Kontrolden kalan her baz/giydirme için en fazla 1 yeniden deneme: +%.3f $ / adet.',
            $est['images'], config('creative.mockup.image_model'), config('creative.mockup.base_image_size'), config('creative.mockup.tryon_image_size'),
            $batch ? 'Batch API %50' : 'anlık', $est['analyses'], $est['usd'], $est['retry_usd'],
        ));
        if (! $this->option('yes') && ! $this->confirm('Devam edilsin mi?', false)) {
            $this->warn('İptal edildi; hiçbir AI çağrısı yapılmadı.');

            return self::FAILURE;
        }

        try {
            // --sync: iş bu süreçte çalışır, kuyruğa ayrıca gönderilmez (çift üretim olmasın).
            $outfit = $pipeline->start($product, $mannequin, $this->option('region'), $this->option('length'), $this->option('garment'), dispatch: ! $this->option('sync'), batch: $batch);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Kombin #{$outfit->id}: bölge={$outfit->region}, boy=" . ($outfit->length ?: '(stilist belirleyecek)') . " ({$outfit->length_source})");

        if ($this->option('sync')) {
            (new GenerateMockupJob($outfit->id))->handle($pipeline);
            $outfit->refresh();
            $this->line("Durum: {$outfit->status}" . ($outfit->error ? " — {$outfit->error}" : ''));
            if ($batch && $outfit->status === MockupOutfit::STATUS_GENERATING) {
                $this->info('Görseller Batch API kuyruğunda; sonuç gelince akış kendiliğinden sürer (durum: php artisan creative:ai-batch --status).');

                return self::SUCCESS;
            }
            $this->line('Pozlar: ' . implode(', ', [config('creative.mockup.master_pose'), ...(array) $outfit->poses]));
            $cost = $outfit->aiCost();
            $this->line(sprintf('Gerçekleşen (kombin + bazlar): %d çağrı, %d görsel ≈ %.3f $. Giydirmeler kuyrukta; toplam için: php artisan creative:ai-usage --outfit=%d', $cost['calls'], $cost['images'], $cost['usd'], $outfit->id));

            return $outfit->status === MockupOutfit::STATUS_READY ? self::SUCCESS : self::FAILURE;
        }

        $this->info(__('creative::messages.mockup.queued', ['product' => $product->name]));

        return self::SUCCESS;
    }
}
