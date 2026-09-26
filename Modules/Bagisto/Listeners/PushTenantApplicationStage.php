<?php

namespace Modules\Bagisto\Listeners;

use Illuminate\Support\Facades\Mail;
use Modules\Bagisto\Jobs\PushTenantApplicationStageToBagisto;
use Modules\Bagisto\Mail\DealerInvitationMail;
use Modules\Tenant\Events\TenantApplicationStageChanged;

/**
 * Bayi portalı başvurusunun adım bildirimleri:
 * - Hesap açıldı (üyelik tamamlama daveti): e-postayı SaaS kendisi gönderir (DealerInvitationMail).
 * - Diğer adımlar (belge aşaması, eksik evrak, red): mağazaya iletilir, e-postayı Bagisto gönderir.
 */
class PushTenantApplicationStage
{
    public function handle(TenantApplicationStageChanged $event): void
    {
        $application = $event->application;

        if ($event->stage === TenantApplicationStageChanged::ACCOUNT_OPENED) {
            if ($application->invitationIsOpen()) {
                Mail::to($application->contact_email, $application->contact_person ?: $application->name)
                    ->queue(new DealerInvitationMail($application));
            }

            return;
        }

        PushTenantApplicationStageToBagisto::dispatch($application->id, $event->stage);
    }
}
