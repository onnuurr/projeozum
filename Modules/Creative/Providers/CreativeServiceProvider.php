<?php

namespace Modules\Creative\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\Drivers\Fal\FalIdmVtonTryOn;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiCaptionGenerator;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiSceneComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCaptionGenerator;
use Modules\Creative\Services\Ai\Drivers\Mock\MockSceneComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockTryOn;
use Modules\Creative\Services\Rendering\PythonRenderer;
use Modules\Creative\Services\Rendering\RendererContract;

class CreativeServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Creative';

    protected string $nameLower = 'creative';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Render motoru config'e göre seçilir. Şimdilik tek implementasyon
        // (Python) var; soyutlama ileride alternatif motorlara izin verir.
        $this->app->bind(RendererContract::class, function () {
            return match (config('creative.render.engine', 'python')) {
                default => $this->app->make(PythonRenderer::class),
            };
        });

        // AI compose sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(SceneComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiSceneComposer::class : MockSceneComposer::class);
        });

        // AI try-on sürücüsü: fal (anahtar varsa), aksi halde mock.
        $this->app->bind(GarmentTryOnContract::class, function ($app) {
            $useFal = config('creative.ai.tryon_driver') === 'fal'
                && config('creative.ai.fal.key');

            return $app->make($useFal ? FalIdmVtonTryOn::class : MockTryOn::class);
        });

        // Caption sürücüsü: gemini metin modeli (anahtar varsa), aksi halde mock.
        $this->app->bind(CaptionGeneratorContract::class, function ($app) {
            $useGemini = config('creative.ai.caption_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiCaptionGenerator::class : MockCaptionGenerator::class);
        });
    }
}
