<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Jobs\RunBackupJob;
use Modules\Superadmin\Models\BackupRun;

/**
 * backup:run komutunun ürettiği backup_runs kayıtlarını superadmin panelinde
 * listeler ve panelden elle yedekleme tetiklemeyi sağlar (bkz. RunBackupJob).
 */
class BackupController extends Controller
{
    public function index(): Response
    {
        $runs = BackupRun::query()->latest('started_at')->limit(50)->get();

        $today = today()->setTime(2, 0);

        return Inertia::render('Superadmin::Backups', [
            'runs'      => $runs,
            'nextRunAt' => (now()->lt($today) ? $today : $today->addDay())->toIso8601String(),
        ]);
    }

    public function run(): RedirectResponse
    {
        if (BackupRun::query()->where('status', BackupRun::STATUS_RUNNING)->exists()) {
            return back()->with('warning', 'Zaten devam eden bir yedekleme var.');
        }

        RunBackupJob::dispatch();

        return back()->with('success', 'Yedekleme kuyruğa alındı. Kuyruk işçisi (queue:work) çalıştığında başlayacak.');
    }
}
