<?php

namespace App\Console\Commands;

use App\Services\ExchangeRates\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * TCMB kur bültenini çekip exchange_rates'e yazar (ExchangeRateService). Zamanlama
 * routes/console.php'de; aynı gün tekrar çalışırsa kayıtları günceller.
 *
 *   php artisan exchange-rates:fetch               # bugünün bülteni
 *   php artisan exchange-rates:fetch --date=2026-09-30
 *   php artisan exchange-rates:fetch --days=30     # son 30 günü geriye doldur (tatiller atlanır)
 */
class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'exchange-rates:fetch {--date= : Belirli bir günün arşiv bülteni (YYYY-AA-GG)} {--days= : Son N günü geriye doldur}';

    protected $description = 'TCMB döviz kurlarını çekip kaydeder.';

    public function handle(ExchangeRateService $rates): int
    {
        $dates = match (true) {
            (bool) $this->option('days') => collect(range((int) $this->option('days') - 1, 0))->map(fn ($d) => now()->subDays($d)->startOfDay()),
            (bool) $this->option('date') => collect([Carbon::parse($this->option('date'))]),
            default => collect([null]),
        };

        $failed = false;
        foreach ($dates as $date) {
            try {
                $saved = $rates->fetchFromTcmb($date);
                $label = $date?->toDateString() ?? 'bugün';
                $saved->isEmpty()
                    ? $this->line("{$label}: bülten yok (hafta sonu/tatil).")
                    : $this->info("{$label}: {$saved->count()} kur kaydedildi ({$saved->first()->rate_date->toDateString()} bülteni).");
            } catch (Throwable $e) {
                $failed = true;
                $this->error(($date?->toDateString() ?? 'bugün').': '.$e->getMessage());
                report($e);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
