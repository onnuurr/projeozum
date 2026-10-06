<?php

namespace Modules\Creative\Console\Commands;

use Illuminate\Console\Command;
use Modules\Creative\Models\AiUsage;
use Modules\Creative\Models\MockupOutfit;

/**
 * AI çağrı maliyet raporu (creative_ai_usages — tahmini, fiyat listesine göre; kesin kaynak fatura).
 *
 *   php artisan creative:ai-usage                 → son 1 gün, sağlayıcı/model/işlem kırılımı
 *   php artisan creative:ai-usage --days=30
 *   php artisan creative:ai-usage --outfit=2      → bir mokap kombininin toplamı
 *   php artisan creative:ai-usage --try=42        → TL karşılığını da göster (1 $ = 42 TL)
 */
class AiUsageReportCommand extends Command
{
    protected $signature = 'creative:ai-usage {--days=1 : Kaç günlük} {--outfit= : Mokap kombini id} {--try= : USD/TRY kuru (opsiyonel)}';

    protected $description = 'AI çağrı sayısı ve tahmini maliyet raporu';

    public function handle(): int
    {
        $rate = $this->option('try') !== null ? (float) $this->option('try') : null;
        $money = fn (float $usd) => sprintf('%.3f $', $usd) . ($rate ? sprintf(' (~%.0f TL)', $usd * $rate) : '');

        if ($id = $this->option('outfit')) {
            $outfit = MockupOutfit::find($id);
            if (! $outfit) {
                $this->error('Kombin bulunamadı.');

                return self::FAILURE;
            }
            $c = $outfit->aiCost();
            $this->info("Kombin #{$outfit->id} (ürün #{$outfit->product_id}, durum: {$outfit->status}): {$c['calls']} çağrı, {$c['images']} görsel ≈ " . $money($c['usd']));

            return self::SUCCESS;
        }

        $since = now()->subDays(max(1, (int) $this->option('days')));
        $rows = AiUsage::where('created_at', '>=', $since)
            ->selectRaw('provider, model, operation, image_size, count(*) as calls, sum(images_out) as images, sum(est_cost_usd) as usd')
            ->groupBy('provider', 'model', 'operation', 'image_size')->orderByDesc('usd')->get();

        $this->table(['Sağlayıcı', 'Model', 'İşlem', 'Boyut', 'Çağrı', 'Görsel', 'Tahmini'], $rows->map(fn ($r) => [
            $r->provider, $r->model, $r->operation, $r->image_size ?? '—', $r->calls, (int) $r->images, $money((float) $r->usd),
        ]));
        $this->info('Toplam (' . $since->format('d.m.Y H:i') . ' sonrası): ' . $rows->sum('calls') . ' çağrı ≈ ' . $money((float) $rows->sum('usd')));

        return self::SUCCESS;
    }
}
