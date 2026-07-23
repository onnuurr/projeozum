<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * architecture:doctor komutunun ürettiği storage/app/architecture-doctor.json raporunu
 * superadmin panelinde görüntüler — Creative\ReviewReportController ile aynı "dosyayı
 * oku, Inertia prop'u olarak geç" deseni (bkz. tools/architecture-doctor).
 */
class ArchitectureDoctorController extends Controller
{
    public function index(): Response
    {
        $path = storage_path('app/architecture-doctor.json');

        return Inertia::render('Superadmin::ArchitectureDoctor', [
            'report' => File::exists($path) ? json_decode(File::get($path), true) : null,
        ]);
    }
}
