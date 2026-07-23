<?php

namespace Tests\Fixtures\AiLayer\Providers;

use Tests\Fixtures\AiLayer\Drivers\GeminiBadDriver;

class BadServiceProvider
{
    public function register(): void
    {
        // Mock'suz, config-switch'siz doğrudan bağlama — Product'ın gerçek ihlaliyle aynı desen.
        $this->app->bind(BadContract::class, GeminiBadDriver::class);
    }
}
