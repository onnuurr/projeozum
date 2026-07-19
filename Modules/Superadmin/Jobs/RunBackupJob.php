<?php

namespace Modules\Superadmin\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Superadmin\Models\BackupRun;
use Modules\Superadmin\Notifications\BackupFailedNotification;
use Modules\Superadmin\Services\BackupService;
use Throwable;

/**
 * Superadmin panelindeki "Şimdi Çalıştır" butonu bu job'ı kuyruğa atar —
 * pg_dump + tar + rclone bir HTTP isteğini bloklamayacak kadar uzun sürebilir
 * (büyük projelerde dakikalarca). Zamanlanmış (02:00) çalışma ise doğrudan
 * backup:run komutu üzerinden senkron yürür (bkz. routes/console.php).
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function handle(BackupService $service): void
    {
        try {
            $service->run('manual');
        } catch (Throwable $e) {
            $backupRun = BackupRun::query()->latest('id')->first();
            if ($backupRun) {
                foreach (User::permission('backups.view')->get() as $admin) {
                    $admin->notify(new BackupFailedNotification($backupRun));
                }
            }
        }
    }
}
