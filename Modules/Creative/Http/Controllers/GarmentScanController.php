<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Models\GarmentLabel;
use Modules\Creative\Models\GarmentScan;
use Modules\Creative\Services\GarmentScanService;

/**
 * Manuel kutu-etiketleme aracı (Faz G.2): fine-tune edilmiş bir tespit modeli
 * henüz yokken (ya da mevcut otomatik tespitler düzeltilmek istendiğinde)
 * giysi parçalarının (yaka/cep/etek vb.) konumu elle işaretlenir. Bu, hem
 * bootstrap veri toplama hem de aktif öğrenme döngüsünün (bkz. ROADMAP.md
 * Faz G) düzeltme adımıdır — creative:train-garment-detector bu kayıtlardan
 * (detections[source=manual]) beslenir.
 */
class GarmentScanController extends Controller
{
    public function index(): Response
    {
        $scans = GarmentScan::query()
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (GarmentScan $s) => [
                'id'              => $s->id,
                'image_url'       => $this->url($s->source_path),
                'status'          => $s->status,
                'model_version'   => $s->model_version,
                'detection_count' => count($s->detections ?? []),
                'created_at'      => $s->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Creative::CreativeGarmentScans', ['scans' => $scans]);
    }

    public function show(GarmentScan $scan): Response
    {
        return Inertia::render('Creative::CreativeGarmentLabeling', [
            'scan' => [
                'id'            => $scan->id,
                'image_url'     => $this->url($scan->source_path),
                'status'        => $scan->status,
                'model_version' => $scan->model_version,
                'detections'    => $scan->detections ?? [],
            ],
            'labels' => GarmentLabel::query()->orderBy('display')->get(['key', 'display', 'group']),
        ]);
    }

    public function storeAnnotation(Request $request, GarmentScan $scan, GarmentScanService $service): RedirectResponse
    {
        $validated = $this->validatedAnnotation($request);

        $service->addManualAnnotation($scan, $validated['bbox'], $validated['label']);

        return back()->with('success', 'Parça eklendi.');
    }

    public function updateAnnotation(Request $request, GarmentScan $scan, string $annotation, GarmentScanService $service): RedirectResponse
    {
        $validated = $this->validatedAnnotation($request);

        $updated = $service->updateAnnotation($scan, $annotation, $validated['bbox'], $validated['label']);
        if ($updated === null) {
            return back()->with('error', 'Güncellenecek parça bulunamadı.');
        }

        return back()->with('success', 'Parça güncellendi.');
    }

    public function destroyAnnotation(GarmentScan $scan, string $annotation, GarmentScanService $service): RedirectResponse
    {
        $service->removeAnnotation($scan, $annotation);

        return back()->with('success', 'Parça silindi.');
    }

    /**
     * @return array{bbox:array{x:float,y:float,w:float,h:float},label:string}
     */
    private function validatedAnnotation(Request $request): array
    {
        $validated = $request->validate([
            'bbox.x'   => ['required', 'numeric', 'between:0,1'],
            'bbox.y'   => ['required', 'numeric', 'between:0,1'],
            'bbox.w'   => ['required', 'numeric', 'between:0.001,1'],
            'bbox.h'   => ['required', 'numeric', 'between:0.001,1'],
            'label'    => ['required', 'string', 'max:60'],
        ]);

        return ['bbox' => $validated['bbox'], 'label' => $validated['label']];
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }
}
