<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Süper admin "Yeni kayıt açık" ayarı değişti. Kernel katmanında durur ki Superadmin'e bağımlı
 * olamayan modüller (ör. Bagisto: bayi portalı başvuru formu) değişikliğe dışarıdan bağlanabilsin.
 * Yalnızca değer gerçekten değiştiğinde yayınlanır (bkz. SettingsController::update()).
 */
class SignupAvailabilityChanged
{
    use Dispatchable;

    public function __construct(public readonly bool $open) {}
}
