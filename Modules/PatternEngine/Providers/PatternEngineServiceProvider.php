<?php

namespace Modules\PatternEngine\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Modules\PatternEngine\Contracts\PatternEngineClient;
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
    }
}
