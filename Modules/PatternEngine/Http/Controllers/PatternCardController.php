<?php

namespace Modules\PatternEngine\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PatternEngine\Http\Requests\SavePatternMeasurementsRequest;
use Modules\PatternEngine\Http\Requests\SavePatternProductionRequest;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Models\PatternMeasurementPoint;
use Modules\PatternEngine\Models\PatternPart;
use Modules\PatternEngine\Models\PatternType;
use Modules\PatternEngine\Services\PatternLibraryService;
use Modules\PatternEngine\Services\PatternMeasurementService;

/**
 * Kalıp Kartı: üretimin ihtiyaç duyduğu kalıp bilgisi — ölçü tabloları (vücut + hazır
 * giysi, kalıp tipinin şablonuna göre), parça/kesim listesi, kumaş sarfiyatı, malzeme.
 * Değerler PDF'ten otomatik dolabilir (source=pdf) ya da elle girilir.
 */
class PatternCardController extends Controller
{
    public function __construct(
        private PatternLibraryService $library,
        private PatternMeasurementService $measurements,
    ) {}

    public function show(Request $request, Pattern $pattern): Response
    {
        $pattern->load(['parts', 'type']);
        $disk = Media::disk();

        $types = PatternType::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $pattern->pattern_type_id))
            ->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('PatternEngine::PatternCard', [
            'pattern' => [
                'id'               => $pattern->id,
                'code'             => $pattern->code,
                'name'             => $pattern->name,
                'status'           => $pattern->status,
                'vendor'           => $pattern->vendor,
                'productType'      => $pattern->product_type,
                'typeId'           => $pattern->pattern_type_id,
                'sizeRange'        => $pattern->size_range,
                'sizes'            => $pattern->sizes ?? [],
                'variants'         => $pattern->variants ?? [],
                'seamAllowance'    => $pattern->seam_allowance_cm !== null ? (float) $pattern->seam_allowance_cm : null,
                'hemAllowance'     => $pattern->hem_allowance_cm !== null ? (float) $pattern->hem_allowance_cm : null,
                'fabricUsage'      => $pattern->fabric_usage,
                'materials'        => $pattern->materials ?? [],
                'extractionStatus' => $pattern->extraction_status,
                'previewUrl'       => $pattern->preview_image_path ? Storage::disk($disk)->url($pattern->preview_image_path) : null,
                'pdfUrl'           => $pattern->pdf_path ? Storage::disk($disk)->url($pattern->pdf_path) : null,
                'dxfUrl'           => $pattern->dxf_path ? Storage::disk($disk)->url($pattern->dxf_path) : null,
                'parts'            => $pattern->parts->map(fn (PatternPart $pt) => [
                    'part_no'     => $pt->part_no,
                    'part_name'   => $pt->part_name,
                    'quantity'    => $pt->quantity,
                    'size_range'  => $pt->size_range,
                    'variants'    => $pt->variants,
                    'fabric_code' => $pt->fabric_code,
                    'notes'       => $pt->notes,
                ])->values(),
            ],
            'measurementTables' => $this->measurements->tables($pattern),
            'types'  => $types,
            'points' => PatternMeasurementPoint::query()->where('is_active', true)
                ->orderBy('basis')->orderBy('sort_order')->get(['id', 'key', 'name', 'basis', 'hint']),
            'canManage' => $request->user()->can('atelier.pattern.manage'),
        ]);
    }

    public function saveMeasurements(SavePatternMeasurementsRequest $request, Pattern $pattern): RedirectResponse
    {
        $this->measurements->save($pattern, $request->validated('tables'));

        return back()->with('success', __('patternengine::messages.pattern.measurements_saved'));
    }

    public function saveProduction(SavePatternProductionRequest $request, Pattern $pattern): RedirectResponse
    {
        $this->library->saveProductionData($pattern, $request->patternData(), $request->validated('parts') ?? []);

        return back()->with('success', __('patternengine::messages.pattern.production_saved'));
    }
}
