<?php

namespace Modules\PatternEngine\Providers;

use App\Support\ModuleSettingsRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\PatternEngine\Contracts\PatternEngineClient;
use Modules\PatternEngine\Services\AiExtraction\Contracts\PatternMetadataExtractorContract;
use Modules\PatternEngine\Services\AiExtraction\Drivers\Gemini\GeminiPatternMetadataExtractor;
use Modules\PatternEngine\Services\AiExtraction\Drivers\Mock\MockPatternMetadataExtractor;
use Modules\PatternEngine\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Modules\PatternEngine\Services\Concept\Drivers\GeminiConceptGenerator;
use Modules\PatternEngine\Services\Concept\Drivers\MockConceptGenerator;
use Modules\PatternEngine\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\PatternEngine\Services\Conversion\Drivers\HttpPdfDxfConverter;
use Modules\PatternEngine\Services\Conversion\Drivers\MockPdfDxfConverter;
use Modules\PatternEngine\Services\Drivers\HttpPatternEngineClient;
use Modules\PatternEngine\Services\Drivers\MockPatternEngineClient;

class PatternEngineServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'PatternEngine';

    protected string $nameLower = 'patternengine';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Kalıp motoru sürücüsü: http (servis URL'i tanımlıysa), aksi halde mock.
        // Model-agnostik soyutlama — sağlayıcı config ile değişir (Atelier'in
        // 'conversion' ayarıyla aynı desen).
        $this->app->bind(PatternEngineClient::class, function ($app) {
            $useHttp = config('patternengine.engine.driver') === 'http'
                && config('patternengine.engine.service_url');

            return $app->make($useHttp ? HttpPatternEngineClient::class : MockPatternEngineClient::class);
        });

        // Aşağıdaki 3 binding 2026-09-22'de Modules/Atelier'den taşındı (bkz.
        // docs/superpowers/specs/2026-09-22-modul-calisma-mantigi-revizyonu-design.md §3).

        // Konsept görsel sürücüsü: gemini (anahtar varsa), aksi halde mock.
        // Model-agnostik soyutlama — sağlayıcı config ile değişir.
        $this->app->bind(ConceptImageGeneratorContract::class, function ($app) {
            $useGemini = config('patternengine.concept.driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiConceptGenerator::class : MockConceptGenerator::class);
        });

        // PDF→DXF dönüştürücü: http (servis URL'i varsa), aksi halde mock.
        $this->app->bind(PdfDxfConverterContract::class, function ($app) {
            $useHttp = config('patternengine.conversion.driver') === 'http'
                && config('patternengine.conversion.service_url');

            return $app->make($useHttp ? HttpPdfDxfConverter::class : MockPdfDxfConverter::class);
        });

        // Kalıp metadata AI çıkarımı (PİLOT): gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(PatternMetadataExtractorContract::class, function ($app) {
            $useGemini = config('patternengine.pattern_metadata_ai.driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiPatternMetadataExtractor::class : MockPatternMetadataExtractor::class);
        });
    }

    public function boot(): void
    {
        parent::boot();

        // Kalıp Ayarları (kalıp tipleri + ölçü noktaları): Atölye sekme satırında çark.
        $this->app->make(ModuleSettingsRegistry::class)->register(
            key: 'pattern',
            url: '/atelier/patterns/settings',
            permission: 'atelier.pattern.settings',
            menuRoots: ['/atelier/patterns'],
        );
    }
}
