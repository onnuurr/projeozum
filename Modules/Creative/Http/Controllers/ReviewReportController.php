<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * creative:review-report komutunun ürettiği haftalık ret analiz raporlarını
 * (storage/app/creative-review-reports/{tarih}.json) listeler ve görüntüler.
 *
 * Bildirim çanındaki "Haftalık giydirme ret raporu hazır" bildirimi buraya
 * yönlendirir (bkz. CreativeReviewReportReadyNotification).
 */
class ReviewReportController extends Controller
{
    private const REPORT_FILENAME_PATTERN = '/^\d{4}-\d{2}-\d{2}\.json$/';

    public function index(): Response
    {
        return Inertia::render('Creative::CreativeReviewReports', [
            'reports' => $this->listReports(),
        ]);
    }

    public function show(string $file): Response|RedirectResponse
    {
        if (! preg_match(self::REPORT_FILENAME_PATTERN, $file)) {
            abort(SymfonyResponse::HTTP_NOT_FOUND);
        }

        $path = storage_path('app/creative-review-reports/' . $file);

        if (! File::exists($path)) {
            return redirect()->route('creative.review-reports.index')
                ->with('error', 'Rapor bulunamadı: ' . $file);
        }

        return Inertia::render('Creative::CreativeReviewReportShow', [
            'file'   => $file,
            'report' => json_decode(File::get($path), true),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function listReports(): array
    {
        $dir = storage_path('app/creative-review-reports');

        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => preg_match(self::REPORT_FILENAME_PATTERN, $f->getFilename()))
            ->sortByDesc(fn ($f) => $f->getFilename())
            ->map(function ($f) {
                $data = json_decode(File::get($f->getPathname()), true) ?? [];

                return [
                    'file'           => $f->getFilename(),
                    'generated_at'   => $data['generated_at'] ?? null,
                    'window_days'    => $data['window_days'] ?? null,
                    'total_reviewed' => $data['tryon_results']['total_reviewed'] ?? 0,
                    'total_rejected' => $data['tryon_results']['total_rejected'] ?? 0,
                    'rejection_rate' => $data['tryon_results']['rejection_rate'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
