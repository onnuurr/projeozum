<?php

namespace App\Services\ExchangeRates;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Döviz kuru altyapısı (paylaşılan, `app/` çekirdek katmanı — her modül kullanabilir).
 *
 * Kaynak TCMB gösterge kurları: günlük bülten (`today.xml`, hafta içi ~15:30'da yayınlanır) ve
 * geçmiş gün arşivi. `exchange-rates:fetch` komutu bülteni çekip `exchange_rates`'e yazar
 * (aynı gün tekrar çekilirse günceller). Belirli bir tarihteki kur, o tarihte ya da öncesindeki
 * en yakın bülten günüdür — hafta sonu/tatilde önceki iş gününün kuru geçerlidir.
 *
 *   $rates->convert(1250, 'USD', '2026-10-02')        // → TL, döviz satış kuru
 *   $rates->rateOn('EUR', now(), ExchangeRate::TYPE_FOREX_BUYING)
 */
class ExchangeRateService
{
    /**
     * TCMB bültenini çekip kaydeder. $date verilirse o günün arşivi (tatil/hafta sonu → bülten yok).
     *
     * @return Collection<int, ExchangeRate> kaydedilen kurlar
     */
    public function fetchFromTcmb(?Carbon $date = null): Collection
    {
        $config = config('exchange_rates.tcmb');
        $url = $date
            ? sprintf($config['archive_url'], $date->format('Ym'), $date->format('dmY'))
            : $config['today_url'];

        $response = Http::timeout((int) $config['timeout'])->get($url);

        if ($response->status() === 404) {
            return collect(); // o gün bülten yayımlanmamış (hafta sonu / resmi tatil)
        }

        $response->throw();

        return $this->storeBulletin($response->body());
    }

    /**
     * TCMB bülten XML'ini kaydeder (test ve elle içe aktarma için ayrı).
     *
     * @return Collection<int, ExchangeRate>
     */
    public function storeBulletin(string $xml): Collection
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($doc === false || ! isset($doc['Date'])) {
            throw new RuntimeException('TCMB kur bülteni okunamadı.');
        }

        // Date özniteliği MM/DD/YYYY
        $rateDate = Carbon::createFromFormat('m/d/Y', (string) $doc['Date'])->startOfDay();
        $number = fn ($v) => trim((string) $v) === '' ? null : (float) $v;

        return DB::transaction(function () use ($doc, $rateDate, $number) {
            $saved = collect();

            foreach ($doc->Currency as $currency) {
                $code = strtoupper((string) $currency['CurrencyCode']);
                if (strlen($code) !== 3 || $number($currency->ForexSelling) === null && $number($currency->BanknoteSelling) === null) {
                    continue; // XDR gibi yalnız çapraz kur veren satırlar
                }

                // whereDate: `date` cast'i saatle yazar; düz eşitlik sürücüye göre eşleşmeyebilir
                $record = ExchangeRate::where('currency_code', $code)->whereDate('rate_date', $rateDate->toDateString())->first()
                    ?? new ExchangeRate(['currency_code' => $code, 'rate_date' => $rateDate]);

                $record->fill([
                    'unit'             => max(1, (int) $currency->Unit),
                    'forex_buying'     => $number($currency->ForexBuying),
                    'forex_selling'    => $number($currency->ForexSelling),
                    'banknote_buying'  => $number($currency->BanknoteBuying),
                    'banknote_selling' => $number($currency->BanknoteSelling),
                    'source'           => 'tcmb',
                ])->save();

                $saved->push($record);
            }

            return $saved;
        });
    }

    /** Verilen tarihte (ya da öncesindeki en yakın bülten gününde) geçerli kur kaydı. */
    public function recordOn(string $currency, Carbon|string|null $date = null): ?ExchangeRate
    {
        return ExchangeRate::query()
            ->where('currency_code', strtoupper($currency))
            ->whereDate('rate_date', '<=', Carbon::parse($date ?? now())->toDateString())
            ->orderByDesc('rate_date')
            ->first();
    }

    /** 1 birim döviz kaç TL. TRY için 1; kayıt yoksa null. */
    public function rateOn(string $currency, Carbon|string|null $date = null, ?string $type = null): ?float
    {
        if (strtoupper($currency) === 'TRY') {
            return 1.0;
        }

        return $this->recordOn($currency, $date)?->perUnit($type ?? config('exchange_rates.default_type'));
    }

    /**
     * Tutarı bir para biriminden diğerine çevirir (TL üzerinden çapraz). Kur bulunamazsa hata —
     * sessizce yanlış tutar üretmektense çağıran tarafı uyarır.
     */
    public function convert(float $amount, string $from, Carbon|string|null $date = null, string $to = 'TRY', ?string $type = null): float
    {
        $fromRate = $this->rateOn($from, $date, $type);
        $toRate = $this->rateOn($to, $date, $type);

        if ($fromRate === null || $toRate === null || $toRate == 0.0) {
            throw new RuntimeException(sprintf('%s/%s kuru bulunamadı (%s).', $from, $to, Carbon::parse($date ?? now())->toDateString()));
        }

        return round($amount * $fromRate / $toRate, 2);
    }

    /**
     * Ana sayfa göstergesi: her para biriminin son kuru ve bir önceki bülten gününe göre değişim.
     *
     * @param  list<string>  $currencies
     * @return list<array{code: string, rate: float, change: float|null, date: string}>
     */
    public function latestWithChange(array $currencies, ?string $type = null): array
    {
        // Migration henüz çalışmadıysa (deploy sırası) ana sayfa hata vermesin, gösterge gizlensin.
        if (! Schema::hasTable('exchange_rates')) {
            return [];
        }

        $type ??= config('exchange_rates.default_type');
        $result = [];

        foreach ($currencies as $code) {
            $records = ExchangeRate::query()
                ->where('currency_code', strtoupper($code))
                ->orderByDesc('rate_date')
                ->limit(2)
                ->get();

            $latest = $records->first()?->perUnit($type);
            if ($latest === null) {
                continue;
            }

            $previous = $records->get(1)?->perUnit($type);

            $result[] = [
                'code'   => strtoupper($code),
                'rate'   => round($latest, 4),
                'change' => $previous ? round(($latest - $previous) / $previous * 100, 2) : null,
                'date'   => $records->first()->rate_date->toDateString(),
            ];
        }

        return $result;
    }
}
