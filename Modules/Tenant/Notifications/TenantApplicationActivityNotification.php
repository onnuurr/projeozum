<?php

namespace Modules\Tenant\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Tenant\Models\TenantApplication;

/**
 * Bayi portalı başvurusunda admin aksiyonu gerektiren adımlar: yeni ön başvuru, resmi belgelerin
 * yüklenmesi, bayinin üyeliğini tamamlaması. tenant-application.approve iznine sahip herkese gider
 * (database kanalı → AppLayout bildirim çanı).
 */
class TenantApplicationActivityNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public const NEW_APPLICATION = 'new_application';

    public const DOCUMENTS_SUBMITTED = 'documents_submitted';

    public const MEMBERSHIP_COMPLETED = 'membership_completed';

    /**
     * @param  self::*  $kind
     */
    public function __construct(private TenantApplication $application, private string $kind) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        $name = $this->application->name;

        [$icon, $iconType, $title, $desc] = match ($this->kind) {
            self::NEW_APPLICATION => ['📝', 'info', 'Yeni bayilik ön başvurusu', "\"{$name}\" ön başvuru yaptı; görüşme sonrası resmi belge aşamasını açabilirsiniz."],
            self::DOCUMENTS_SUBMITTED => ['📎', 'info', 'Resmi belgeler yüklendi', "\"{$name}\" resmi belgelerini yükledi; evrak incelemesi bekliyor."],
            default => ['✅', 'success', 'Bayi üyeliğini tamamladı', "\"{$name}\" parolasını oluşturdu; hesabı aktif."],
        };

        return [
            'icon' => $icon,
            'iconType' => $iconType,
            'title' => $title,
            'desc' => $desc,
            'link' => '/tenants/applications',
            'kind' => 'tenant_application.'.$this->kind,
            'application_id' => $this->application->id,
        ];
    }
}
