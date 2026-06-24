<?php

namespace Modules\Atelier\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Atelier\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Modules\Atelier\Services\Concept\Drivers\GeminiConceptGenerator;
use Modules\Atelier\Services\Concept\Drivers\MockConceptGenerator;
use Modules\Atelier\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\Atelier\Services\Conversion\Drivers\HttpPdfDxfConverter;
use Modules\Atelier\Services\Conversion\Drivers\MockPdfDxfConverter;

class AtelierServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Atelier';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'atelier';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Konsept görsel sürücüsü: gemini (anahtar varsa), aksi halde mock.
        // Model-agnostik soyutlama — sağlayıcı config ile değişir (yol haritası §3).
        $this->app->bind(ConceptImageGeneratorContract::class, function ($app) {
            $useGemini = config('atelier.concept.driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiConceptGenerator::class : MockConceptGenerator::class);
        });

        // PDF→DXF dönüştürücü: http (servis URL'i varsa), aksi halde mock.
        $this->app->bind(PdfDxfConverterContract::class, function ($app) {
            $useHttp = config('atelier.conversion.driver') === 'http'
                && config('atelier.conversion.service_url');

            return $app->make($useHttp ? HttpPdfDxfConverter::class : MockPdfDxfConverter::class);
        });
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
