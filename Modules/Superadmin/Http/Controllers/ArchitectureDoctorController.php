<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Jobs\RunArchitectureDoctorFixJob;
use Modules\Superadmin\Models\ArchitectureDoctorFixRun;

/**
 * architecture:doctor komutunun ürettiği storage/app/architecture-doctor.json raporunu
 * superadmin panelinde görüntüler — Creative\ReviewReportController ile aynı "dosyayı
 * oku, Inertia prop'u olarak geç" deseni (bkz. tools/architecture-doctor). Panelden ayrıca
 * anlık yeniden tarama (scan) ve agent tabanlı otomatik düzeltme (fix) tetiklenebilir.
 */
class ArchitectureDoctorController extends Controller
{
    public function index(): Response
    {
        $path = storage_path('app/architecture-doctor.json');

        return Inertia::render('Superadmin::ArchitectureDoctor', [
            'report' => File::exists($path) ? json_decode(File::get($path), true) : null,
            'runs' => ArchitectureDoctorFixRun::query()->latest('id')->limit(20)->get(),
        ]);
    }

    public function scan(): RedirectResponse
    {
        Artisan::call('architecture:doctor', ['--json' => true]);

        return back()->with('success', 'Rapor güncellendi.');
    }

    public function fix(): RedirectResponse
    {
        if (! config('superadmin.architecture_doctor.enabled')) {
            return back()->with('warning', 'Otomatik düzeltme sunucuda henüz etkinleştirilmedi.');
        }

        if (ArchitectureDoctorFixRun::query()->whereIn('status', [
            ArchitectureDoctorFixRun::STATUS_QUEUED,
            ArchitectureDoctorFixRun::STATUS_RUNNING,
        ])->exists()) {
            return back()->with('warning', 'Zaten devam eden bir düzeltme çalışması var.');
        }

        $fixRun = ArchitectureDoctorFixRun::create([
            'status' => ArchitectureDoctorFixRun::STATUS_QUEUED,
            'triggered_by_user_id' => auth()->id(),
        ]);

        RunArchitectureDoctorFixJob::dispatch($fixRun);

        return back()->with('success', 'Düzeltme kuyruğa alındı. Kuyruk işçisi (queue:work) çalıştığında başlayacak.');
    }
}
