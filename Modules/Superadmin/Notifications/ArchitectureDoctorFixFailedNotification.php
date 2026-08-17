<?php

namespace Modules\Superadmin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Superadmin\Models\ArchitectureDoctorFixRun;

/**
 * Panelden tetiklenen "Otomatik Düzelt" çalışması başarısız olduğunda
 * architecture-doctor.manage iznine sahip hesaplara gider (bkz.
 * BackupFailedNotification ile aynı desen).
 */
class ArchitectureDoctorFixFailedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private ArchitectureDoctorFixRun $fixRun) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => '⚠️',
            'iconType' => 'error',
            'title' => 'Mimari Doktor otomatik düzeltme başarısız oldu',
            'desc' => mb_substr((string) $this->fixRun->error_message, 0, 200),
            'link' => '/superadmin/architecture-doctor',
            'kind' => 'architecture_doctor_fix_failed',
            'fix_run_id' => $this->fixRun->id,
        ];
    }
}
