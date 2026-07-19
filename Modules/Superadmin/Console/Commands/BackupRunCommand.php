<?php

namespace Modules\Superadmin\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Superadmin\Models\BackupRun;
use Modules\Superadmin\Notifications\BackupFailedNotification;
use Modules\Superadmin\Services\BackupService;
use Throwable;

/**
 * Veritabanı + proje dosyaları yedeğini alır, rclone ile Google Drive'a
 * kopyalar ve sonucu backup_runs tablosuna işler (superadmin panelinden
 * takip edilir — Modules/Superadmin/Resources/assets/js/Pages/Backups.vue).
 *
 * Zamanlaması routes/console.php'de: Schedule::command('backup:run')->dailyAt('02:00').
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--manual : Panelden elle tetiklendi}';

    protected $description = 'Veritabanı + proje dosyaları yedeğini alır ve rclone ile uzak depolamaya kopyalar';

    public function __construct(private BackupService $backupService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $triggeredBy = $this->option('manual') ? 'manual' : 'schedule';

        $this->components->info("Yedekleme başlatıldı ({$triggeredBy})");

        try {
            $backupRun = $this->backupService->run($triggeredBy);

            $this->components->info(sprintf(
                'Yedekleme tamamlandı: %s (%d sn, DB %s, dosyalar %s)',
                $backupRun->remote_path,
                $backupRun->duration_seconds,
                $this->formatBytes($backupRun->db_dump_bytes),
                $this->formatBytes($backupRun->files_bytes),
            ));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error("Yedekleme başarısız: {$e->getMessage()}");
            $this->notifyFailure($e);

            return self::FAILURE;
        }
    }

    private function notifyFailure(Throwable $e): void
    {
        $backupRun = BackupRun::query()->latest('id')->first();
        if (! $backupRun) {
            return;
        }

        foreach (User::permission('backups.view')->get() as $admin) {
            $admin->notify(new BackupFailedNotification($backupRun));
        }
    }

    private function formatBytes(?int $bytes): string
    {
        if (! $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
    }
}
