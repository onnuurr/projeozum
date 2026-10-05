<?php

namespace Modules\Creative\Services\Ai;

use Illuminate\Contracts\Container\Container;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Drivers\Fal\FalFashnTryOn;
use Modules\Creative\Services\Ai\Drivers\Fal\FalFluxVtoTryOn;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOn;
use Modules\Creative\Services\Ai\Drivers\Mock\MockTryOn;

/**
 * Ürün giydirme (try-on) sürücüsünü ada göre çözer: gemini (Nano Banana 2) | fal | mock.
 *
 * Varsayılan sürücü config('creative.ai.tryon_driver'); Ürün Giydirme sayfasındaki
 * Gemini/fal.ai seçimi her istekte bunu ezebilir (meta.tryon_driver_requested, bkz.
 * ProductOnModelService::queue/generate). Seçilen sürücünün anahtarı yoksa mock'a düşülür.
 * fal tarafında iki model şeması desteklenir — `creative.ai.fal.model` (FAL_TRYON_MODEL)
 * değerine göre fashn/tryon (FalFashnTryOn) veya flux-pro/v1/vto (FalFluxVtoTryOn).
 */
class TryOnDriverResolver
{
    /** Sayfada seçilebilen gerçek sürücüler. */
    public const DRIVERS = ['gemini', 'fal'];

    public function __construct(private Container $app) {}

    public function defaultDriver(): string
    {
        return (string) config('creative.ai.tryon_driver');
    }

    /** @return array<string,bool> sürücü => anahtarı tanımlı mı */
    public function availability(): array
    {
        return [
            'gemini' => (bool) config('creative.ai.gemini.api_key'),
            'fal'    => (bool) config('creative.ai.fal.key'),
        ];
    }

    /** @return class-string<GarmentTryOnContract> */
    public function classFor(?string $driver): string
    {
        $available = $this->availability();
        $isFashn   = str_contains((string) config('creative.ai.fal.model'), 'fashn');

        return match (true) {
            $driver === 'gemini' && $available['gemini']       => GeminiTryOn::class,
            $driver === 'fal' && $available['fal'] && $isFashn => FalFashnTryOn::class,
            $driver === 'fal' && $available['fal']             => FalFluxVtoTryOn::class,
            default                                            => MockTryOn::class,
        };
    }

    /**
     * İstenen sürücüyü döndürür. İstek yoksa / varsayılanla aynıysa container binding'i
     * kullanılır (testlerin GarmentTryOnContract'a bağladığı sahte sürücü de böylece korunur).
     */
    public function make(?string $driver): GarmentTryOnContract
    {
        if ($driver === null || ! in_array($driver, self::DRIVERS, true) || $driver === $this->defaultDriver()) {
            return $this->app->make(GarmentTryOnContract::class);
        }

        return $this->app->make($this->classFor($driver));
    }
}
