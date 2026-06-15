<?php

namespace App\Listeners;

use App\Logging\ActivityLogger;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function handle(Failed $event): void
    {
        // KVKK: credentials dizisini ASLA doğrudan geçirme — parola içerir.
        // Yalnızca e-posta adresini ayrıştırıp properties'a koy;
        // ActivityLogger bunu LogSanitizer üzerinden maskeleyerek kaydeder.
        $this->logger->log('auth.login_failed', 'Başarısız giriş denemesi', [
            'module'     => 'auth',
            'level'      => 'warning',
            'properties' => [
                'email' => $event->credentials['email'] ?? null,
            ],
        ]);
    }
}
