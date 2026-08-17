<?php

namespace Modules\Superadmin\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Superadmin\Models\ArchitectureDoctorFixRun;
use Modules\Superadmin\Notifications\ArchitectureDoctorFixFailedNotification;
use Modules\Superadmin\Services\ArchitectureDoctorFixService;
use Throwable;

/**
 * Superadmin panelindeki "Otomatik Düzelt" butonu bu job'ı kuyruğa atar —
 * izole worktree açma + headless Claude CLI çalıştırma dakikalarca sürebilir
 * (bkz. ArchitectureDoctorFixService), bir HTTP isteğini bloklayamaz.
 */
class RunArchitectureDoctorFixJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public ArchitectureDoctorFixRun $fixRun)
    {
        $this->timeout = (int) config('superadmin.architecture_doctor.fix_timeout', 3600) + 60;
    }

    public function handle(ArchitectureDoctorFixService $service): void
    {
        try {
            $service->run($this->fixRun);
        } catch (Throwable $e) {
            foreach (User::permission('architecture-doctor.manage')->get() as $admin) {
                $admin->notify(new ArchitectureDoctorFixFailedNotification($this->fixRun->fresh()));
            }
        }
    }
}
