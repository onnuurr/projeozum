<?php

namespace Modules\Creative\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
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
    }
}
