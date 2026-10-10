<?php

namespace Tests\Feature;

use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\ExchangeRates\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Döviz kuru altyapısı: TCMB bülteni, tarihli kur (tatilde önceki iş günü), çevrim, ana sayfa göstergesi. */
class ExchangeRateTest extends TestCase
{
    use RefreshDatabase;

    private function bulletin(string $date, float $usd, float $eur): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Tarih_Date Tarih="x" Date="{$date}" Bulten_No="2026/1">
  <Currency CrossOrder="0" Kod="USD" CurrencyCode="USD"><Unit>1</Unit><ForexBuying>{$usd}</ForexBuying><ForexSelling>{$usd}</ForexSelling><BanknoteBuying></BanknoteBuying><BanknoteSelling></BanknoteSelling></Currency>
  <Currency CrossOrder="9" Kod="EUR" CurrencyCode="EUR"><Unit>1</Unit><ForexBuying>{$eur}</ForexBuying><ForexSelling>{$eur}</ForexSelling><BanknoteBuying/><BanknoteSelling/></Currency>
  <Currency CrossOrder="13" Kod="JPY" CurrencyCode="JPY"><Unit>100</Unit><ForexBuying>32.50</ForexBuying><ForexSelling>33.00</ForexSelling><BanknoteBuying/><BanknoteSelling/></Currency>
  <Currency CrossOrder="18" Kod="XDR" CurrencyCode="XDR"><Unit>1</Unit><ForexBuying>66.1</ForexBuying><ForexSelling/><BanknoteBuying/><BanknoteSelling/></Currency>
</Tarih_Date>
XML;
    }

    public function test_bulletin_is_stored_idempotently_and_dated_lookup_falls_back(): void
    {
        $rates = app(ExchangeRateService::class);
        $rates->storeBulletin($this->bulletin('10/01/2026', 48.90, 57.20)); // Perşembe
        $rates->storeBulletin($this->bulletin('10/02/2026', 49.00, 57.40)); // Cuma
        $rates->storeBulletin($this->bulletin('10/02/2026', 49.05, 57.40)); // aynı gün yeniden

        $this->assertSame(2, ExchangeRate::where('currency_code', 'USD')->count());
        $this->assertSame(0, ExchangeRate::where('currency_code', 'XDR')->count(), 'satış kuru olmayan satır atlanır');

        $this->assertSame(49.05, $rates->rateOn('USD', '2026-10-02'));
        $this->assertSame(49.05, $rates->rateOn('usd', '2026-10-04'), 'pazar → cuma bülteni');
        $this->assertSame(48.90, $rates->rateOn('USD', '2026-10-01'));
        $this->assertNull($rates->rateOn('USD', '2026-09-30'));
        $this->assertSame(0.33, $rates->rateOn('JPY', '2026-10-02'), '100 birimlik kur birime bölünür');
        $this->assertSame(1.0, $rates->rateOn('TRY'));

        $this->assertSame(61312.5, $rates->convert(1250, 'USD', '2026-10-02'));
        $this->assertSame(1000.0, round($rates->convert(57400, 'TRY', '2026-10-02', 'EUR'), 2));

        $this->expectException(RuntimeException::class);
        $rates->convert(10, 'USD', '2026-01-01');
    }

    public function test_fetch_command_reads_tcmb_and_skips_holidays(): void
    {
        Http::fake([
            'www.tcmb.gov.tr/kurlar/today.xml' => Http::response($this->bulletin('10/02/2026', 49.0, 57.4)),
            'www.tcmb.gov.tr/kurlar/202610/03102026.xml' => Http::response('', 404),
        ]);

        $this->artisan('exchange-rates:fetch')->assertSuccessful();
        $this->artisan('exchange-rates:fetch', ['--date' => '2026-10-03'])->expectsOutputToContain('bülten yok')->assertSuccessful();

        $this->assertSame(3, ExchangeRate::count());
    }

    public function test_dashboard_receives_latest_rates_with_change(): void
    {
        $rates = app(ExchangeRateService::class);
        $rates->storeBulletin($this->bulletin('10/01/2026', 48.0, 57.4));
        $rates->storeBulletin($this->bulletin('10/02/2026', 49.2, 57.4));

        $this->withoutVite();
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('exchangeRates.0.code', 'USD')
                ->where('exchangeRates.0.rate', 49.2)
                ->where('exchangeRates.0.change', 2.5)
                ->where('exchangeRates.1.code', 'EUR')
                ->where('exchangeRates.1.change', 0)
                ->where('exchangeRates.0.date', '2026-10-02'));
    }
}
