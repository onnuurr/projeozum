<?php

namespace App\Listeners;

use App\Logging\ActivityLogger;
use Illuminate\Auth\Events\PasswordReset;

class LogPasswordReset
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(PasswordReset $event): void
    {
        $this->logger->log('auth.password_reset', 'Parola sıfırlandı', [
            'causer' => $event->user,
            'module' => 'auth',
        ]);
    }
}
