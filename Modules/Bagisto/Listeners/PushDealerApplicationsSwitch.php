<?php

namespace Modules\Bagisto\Listeners;

use App\Events\SignupAvailabilityChanged;
use Modules\Bagisto\Services\StorefrontFeatureService;

/**
 * Süper admin "Yeni kayıt açık" anahtarını değiştirince bayi portalının başvuru formunu
 * açıp kapatmak için özellik değerlerini mağazaya yeniden gönderir.
 */
class PushDealerApplicationsSwitch
{
    public function __construct(private StorefrontFeatureService $features) {}

    public function handle(SignupAvailabilityChanged $event): void
    {
        $this->features->push();
    }
}
