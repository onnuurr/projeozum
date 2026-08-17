<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Superadmin\Services\FailedJobService;

/**
 * `failed_jobs` tablosunu (queue:work'ün ürettiği) superadmin panelinde listeler;
 * dashboard/ayarlar sayfasındaki "Başarısız iş" sayacı buraya link verir. Panelden
 * toplu yeniden deneme (queue:retry) ve toplu temizleme (queue:flush) tetiklenebilir.
 */
class FailedJobController extends Controller
{
    public function __construct(private readonly FailedJobService $failedJobs)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Superadmin::FailedJobs', [
            'jobs' => $this->failedJobs->list(),
        ]);
    }

    public function retryAll(): RedirectResponse
    {
        $count = $this->failedJobs->count();

        if ($count === 0) {
            return back()->with('warning', 'Başarısız iş yok.');
        }

        $this->failedJobs->retryAll();

        return back()->with('success', "{$count} iş yeniden kuyruğa alındı.");
    }

    public function flush(): RedirectResponse
    {
        $this->failedJobs->flush();

        return back()->with('success', 'Başarısız iş kayıtları temizlendi.');
    }
}
