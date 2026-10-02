<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Services\DealerMailTemplateService;
use Modules\Tenant\Events\TenantApplicationStageChanged;

/**
 * Bayi portalı başvurusunun tüm adım e-postaları SaaS'tan gider (DealerApplicationMail); metinler
 * İş Ortakları › Başvuru E-postaları sayfasında düzenlenir. Mağaza (Bagisto) başvuru kaydı
 * tutmadığı için adımlar ona iletilmez.
 */
class SendTenantApplicationStageMail
{
    public function __construct(private DealerMailTemplateService $templates) {}

    public function handle(TenantApplicationStageChanged $event): void
    {
        $this->templates->send($event->application, $event->stage);
    }
}
