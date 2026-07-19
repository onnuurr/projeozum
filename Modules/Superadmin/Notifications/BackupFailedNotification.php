<?php

namespace Modules\Superadmin\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Modules\Superadmin\Models\BackupRun;

/**
 * Gece 02:00 backup:run başarısız olduğunda backups.view iznine sahip
 * hesaplara gider (bkz. CreativeReviewReportReadyNotification ile aynı desen).
 */
class BackupFailedNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public function __construct(private BackupRun $backupRun) {}

    /** @return array<int,string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string,mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon'          => '⚠️',
            'iconType'      => 'error',
            'title'         => 'Yedekleme başarısız oldu',
            'desc'          => mb_substr((string) $this->backupRun->error_message, 0, 200),
            'link'          => '/superadmin/backups',
            'kind'          => 'backup_failed',
            'backup_run_id' => $this->backupRun->id,
        ];
    }
}
