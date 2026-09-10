<?php

namespace Modules\PatternEngine\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PatternEngine\DTOs\DesignParamsDTO;
use Modules\PatternEngine\Exceptions\PatternGenerationFailedException;
use Modules\PatternEngine\Http\Requests\DigitizeRequest;
use Modules\PatternEngine\Http\Requests\GeneratePatternRequest;
use Modules\PatternEngine\Models\PatternGenerationJob;
use Modules\PatternEngine\Services\PatternEngineService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatternEngineController extends Controller
{
    public function __construct(private readonly PatternEngineService $service) {}

    public function index(): Response
    {
        return Inertia::render('PatternEngine::PatternEngineJobs', [
            'jobs' => PatternGenerationJob::query()
                ->latest()
                ->limit(50)
                ->get([
                    'id', 'garment', 'size', 'graded_sizes', 'profile', 'status',
                    'result_dxf_path', 'validation_report', 'error', 'created_at',
                ]),
            'engineOnline' => $this->service->probe(),
            // Beden listesi profile göre değişir (§5.2, Sprint 4) — statik değil,
            // her profilin kendi `sizes` anahtarları frontend'de seçilen profile göre süzülür.
            'profiles' => $this->service->listProfiles(),
        ]);
    }

    public function store(GeneratePatternRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $designParams = DesignParamsDTO::fromArray($data['design_params'] ?? []);
        $gradeSizes = array_values(array_filter((array) ($data['grade_sizes'] ?? [])));

        if ($gradeSizes !== []) {
            $this->service->queueGraded(
                garment: $data['garment'],
                baseSize: $data['size'],
                targetSizes: $gradeSizes,
                profile: $data['profile'] ?? 'ts_default',
                designParams: $designParams,
            );
        } else {
            $this->service->queue(
                garment: $data['garment'],
                size: $data['size'],
                profile: $data['profile'] ?? 'ts_default',
                designParams: $designParams,
            );
        }

        return redirect()->route('patternengine.index');
    }

    /**
     * Technical flat görselinden `DesignParams` ÖNERİSİ döner (§4.1, Sprint 5).
     *
     * Kalıp üretmez — yalnızca formu önerilen değerlerle önceden doldurmak için
     * JSON döner (AI omurga değil sağlayıcıdır, §P2). Yükleme geçicidir, iş
     * bitince silinir.
     */
    public function digitize(DigitizeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $disk = (string) config('patternengine.engine.disk');

        $tempPath = $request->file('image')->store('pattern-engine/digitize-uploads', $disk);

        try {
            $suggestion = $this->service->digitize(
                garment: $data['garment'],
                size: $data['size'],
                profile: $data['profile'] ?? 'ts_default',
                absoluteImagePath: Storage::disk($disk)->path($tempPath),
            );
        } catch (PatternGenerationFailedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } finally {
            Storage::disk($disk)->delete($tempPath);
        }

        return response()->json([
            'design_params' => $suggestion->designParams->toArray(),
            'silhouette'    => $suggestion->silhouette,
            'warnings'      => $suggestion->warnings,
        ]);
    }

    public function download(PatternGenerationJob $job): StreamedResponse
    {
        abort_unless($job->status === PatternGenerationJob::STATUS_DONE && $job->result_dxf_path, 404);

        $disk = (string) config('patternengine.engine.disk');

        abort_unless(Storage::disk($disk)->exists($job->result_dxf_path), 404);

        return Storage::disk($disk)->download(
            $job->result_dxf_path,
            sprintf('%s-%s.dxf', $job->garment, $job->size),
        );
    }
}
