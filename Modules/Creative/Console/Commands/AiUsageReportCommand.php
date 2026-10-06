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
 *   php artisan creative:ai-usage --try=49        → TL karşılığı için kuru elle ver (varsayılan: TCMB son USD kuru)
 */
class AiUsageReportCommand extends Command
{
    protected $signature = 'creative:ai-usage {--days=1 : Kaç günlük} {--outfit= : Mokap kombini id} {--try= : USD/TRY kuru (boşsa TCMB son kuru)}';

    protected $description = 'AI çağrı sayısı ve tahmini maliyet raporu';

    public function handle(): int
    {
        $rate = $this->option('try') !== null ? (float) $this->option('try') : $this->tcmbUsdRate();
        if ($rate) {
            $this->line(sprintf('Kur: 1 $ = %.4f TL%s. Google faturası kendi kurunu ve vergiyi uygular; bu tutarlar tahmindir.', $rate, $this->option('try') !== null ? ' (elle verildi)' : ' (TCMB son bülten)'));
        }
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

    /** TCMB son USD kuru (App\Services\ExchangeRates); servis/tablo yoksa ya da veri yoksa null — rapor yalnız $ gösterir. */
    private function tcmbUsdRate(): ?float
    {
        $service = 'App\\Services\\ExchangeRates\\ExchangeRateService';
        try {
            return class_exists($service) ? (app($service)->rateOn('USD') ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
