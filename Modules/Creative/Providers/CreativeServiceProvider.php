<?php

namespace Modules\Creative\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\MannequinComposerContract;
use Modules\Creative\Services\Ai\Contracts\MannequinPoseComposerContract;
use Modules\Creative\Services\Ai\Contracts\PosePreviewComposerContract;
use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\Drivers\Fal\FalIdmVtonTryOn;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiCaptionGenerator;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiMannequinComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiMannequinPoseComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiPosePreviewComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiSceneComposer;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOn;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCaptionGenerator;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMannequinComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockMannequinPoseComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockPosePreviewComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockSceneComposer;
use Modules\Creative\Services\Ai\Drivers\Mock\MockTryOn;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Creative\Services\Enhancement\NullImageEnhancer;
use Modules\Creative\Services\Enhancement\PythonImageEnhancer;
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

        // Görsel iyileştirme sürücüsü: enabled + driver=python ise Python (opencv),
        // aksi halde passthrough (Null). Üretim sonrası upscale/keskinleştirme katmanı.
        $this->app->bind(ImageEnhancerContract::class, function ($app) {
            $usePython = config('creative.enhance.enabled')
                && config('creative.enhance.driver') === 'python';

            return $app->make($usePython ? PythonImageEnhancer::class : NullImageEnhancer::class);
        });

        // AI compose sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(SceneComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiSceneComposer::class : MockSceneComposer::class);
        });

        // AI manken sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(MannequinComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiMannequinComposer::class : MockMannequinComposer::class);
        });

        // AI manken pozu sürücüsü: gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(MannequinPoseComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiMannequinPoseComposer::class : MockMannequinPoseComposer::class);
        });

        // AI poz önizleme sürücüsü (bağımsız nötr figür): gemini (anahtar varsa), aksi halde mock.
        $this->app->bind(PosePreviewComposerContract::class, function ($app) {
            $useGemini = config('creative.ai.compose_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiPosePreviewComposer::class : MockPosePreviewComposer::class);
        });

        // AI try-on sürücüsü: gemini (Nano Banana 2) → fal (idm-vton) → mock.
        // Seçilen sürücünün anahtarı yoksa mock'a düşülür.
        $this->app->bind(GarmentTryOnContract::class, function ($app) {
            $driver = config('creative.ai.tryon_driver');
            $hasGemini = (bool) config('creative.ai.gemini.api_key');
            $hasFal    = (bool) config('creative.ai.fal.key');

            $class = match (true) {
                $driver === 'gemini' && $hasGemini => GeminiTryOn::class,
                $driver === 'fal' && $hasFal       => FalIdmVtonTryOn::class,
                default                            => MockTryOn::class,
            };

            return $app->make($class);
        });

        // Caption sürücüsü: gemini metin modeli (anahtar varsa), aksi halde mock.
        $this->app->bind(CaptionGeneratorContract::class, function ($app) {
            $useGemini = config('creative.ai.caption_driver') === 'gemini'
                && config('creative.ai.gemini.api_key');

            return $app->make($useGemini ? GeminiCaptionGenerator::class : MockCaptionGenerator::class);
        });
    }
}
