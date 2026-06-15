<?php

namespace App\Listeners;

use App\Logging\ActivityLogger;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Login $event): void
    {
        $this->logger->log('auth.login', 'Kullanıcı giriş yaptı', [
            'causer' => $event->user,
            'module' => 'auth',
        ]);
    }
}
