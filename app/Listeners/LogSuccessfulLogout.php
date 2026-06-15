<?php

namespace App\Listeners;

use App\Logging\ActivityLogger;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Logout $event): void
    {
        $this->logger->log('auth.logout', 'Kullanıcı çıkış yaptı', [
            'causer' => $event->user,
            'module' => 'auth',
        ]);
    }
}
