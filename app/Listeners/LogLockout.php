<?php

namespace App\Listeners;

use App\Logging\ActivityLogger;
use Illuminate\Auth\Events\Lockout;

class LogLockout
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Lockout $event): void
    {
        // E-posta varsa properties'a al — ActivityLogger LogSanitizer ile maskeler.
        $email = $event->request->input('email');

        $opts = [
            'module' => 'auth',
            'level'  => 'warning',
        ];

        if ($email !== null && $email !== '') {
            $opts['properties'] = ['email' => $email];
        }

        $this->logger->log('auth.lockout', 'Çok fazla başarısız deneme, kilitlendi', $opts);
    }
}
