<?php

namespace Tests\Fixtures\AiLayer\Providers;

use Tests\Fixtures\AiLayer\Drivers\GeminiGoodDriver;
use Tests\Fixtures\AiLayer\Drivers\MockGoodDriver;

class GoodServiceProvider
{
    public function register(): void
    {
        // Config-tabanlı Gemini/Mock seçimi — Creative/AtelierServiceProvider ile aynı desen.
        $this->app->bind(GoodContract::class, function ($app) {
            $useGemini = config('fixture.good.driver') === 'gemini';

            return $app->make($useGemini ? GeminiGoodDriver::class : MockGoodDriver::class);
        });
    }
}
