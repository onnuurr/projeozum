<?php

namespace Modules\PatternEngine\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\PatternEngine\Http\Requests\ImportPatternFromUrlRequest;
use Modules\PatternEngine\Http\Requests\SaveTracedPatternRequest;
use Modules\PatternEngine\Jobs\ExtractPatternFromPdfJob;
use Modules\PatternEngine\Jobs\ExtractPatternFromPhotoJob;
use Modules\PatternEngine\Models\Pattern;
use Modules\PatternEngine\Services\Conversion\Contracts\PdfDxfConverterContract;
use Modules\PatternEngine\Services\PatternLibraryService;
use Modules\PatternEngine\Services\PatternUrlImportService;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PatternController extends Controller
{
    public function __construct(
        private PatternLibraryService $library,
        private PdfDxfConverterContract $converter,
        private PatternUrlImportService $urlImporter,
    ) {}

    public function index(Request $request): Response
    {
        $disk    = Media::disk();
        $search  = trim((string) $request->input('search', ''));
        $type    = (string) $request->input('product_type', '');
        $status  = (string) $request->input('status', '');
        $tag     = trim((string) $request->input('tag', ''));

        $patterns = Pattern::query()
            ->with(['parts:id,pattern_id,part_no,part_name,quantity,size_range,variants,fabric_code,notes', 'tags:id,pattern_id,tag'])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('code', 'ilike', "%{$search}%")
                ->orWhere('vendor', 'ilike', "%{$search}%")))
            ->when($type !== '', fn ($q) => $q->where('product_type', $type))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($tag !== '', fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tag', $tag)))
            ->latest()
            ->limit(120)
            ->get()
            ->map(fn (Pattern $p) => [
                'id'           => $p->id,
                'code'         => $p->code,
                'name'         => $p->name,
                'productType'  => $p->product_type,
                'sizeRange'    => $p->size_range,
                'vendor'       => $p->vendor,
                'collection'   => $p->collection,
                'status'       => $p->status,
                'extractionStatus' => $p->extraction_status,
                'extractionError'  => $p->extraction_error,
                'scaleVerified'=> $p->scale_verified,
                'scaleDeviation'=> $p->scale_deviation_mm !== null ? (float) $p->scale_deviation_mm : null,
                'notes'        => $p->notes,
                'previewUrl'   => $p->preview_image_path ? Storage::disk($disk)->url($p->preview_image_path) : null,
                'dxfUrl'       => $p->dxf_path ? Storage::disk($disk)->url($p->dxf_path) : null,
                'pdfUrl'       => $p->pdf_path ? Storage::disk($disk)->url($p->pdf_path) : null,
                // partNo/variants/fabricCode/notes Kalıp Kartı'nda düzenlenir; çekmece kaydında
                // kaybolmasınlar diye burada da taşınır (syncParts tüm listeyi yeniden yazar).
                'parts'        => $p->parts->map(fn ($pt) => [
                    'partName' => $pt->part_name, 'quantity' => $pt->quantity, 'sizeRange' => $pt->size_range,
                    'partNo' => $pt->part_no, 'variants' => $pt->variants, 'fabricCode' => $pt->fabric_code, 'notes' => $pt->notes,
                ]),
                'tags'         => $p->tags->pluck('tag'),
                'createdAt'    => $p->created_at?->format('Y-m-d'),
            ]);

        // Birincil filtre: ürün tipi (yol haritası §9.4). Etiketler ileride genişler.
        $productTypes = Pattern::query()->select('product_type')->distinct()
            ->orderBy('product_type')->pluck('product_type');
        $allTags = Pattern::query()->join('pattern_tags', 'patterns.id', '=', 'pattern_tags.pattern_id')
            ->select('pattern_tags.tag')->distinct()->orderBy('tag')->pluck('tag');

        return Inertia::render('PatternEngine::Patterns', [
            'patterns'     => $patterns,
            'productTypes' => $productTypes,
            'allTags'      => $allTags,
            'filters'      => ['search' => $search, 'product_type' => $type, 'status' => $status, 'tag' => $tag],
            'canManage'    => $request->user()?->can('atelier.pattern.manage') ?? false,
        ]);
    }

    /**
     * Toplu PDF yükle → her biri için 'inceleniyor' taslak kalıp + arka plan çıkarımı.
     */
    public function importPdf(Request $request): RedirectResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimetypes:application/pdf', 'max:51200'],
        ]);

        $imported = 0;
        $skipped  = [];
        foreach ($request->file('files') as $pdf) {
            // Ön-kontrol. Servis kapalıysa probe null döner → kontrol atlanır, akış engellenmez.
            $probe = $this->converter->probe($pdf->getRealPath());
            $kind  = $probe['kind'] ?? null;

            // Yalnızca boş/geçersiz baştan elenir. Raster taramalar artık çıkarım kuyruğuna
            // girer: motor otomatik vektörleştirmeyi dener; başarısızsa applyExtraction
            // taslağı needs_tracing'e alır (operatör tracer ile elle izler).
            if (in_array($kind, ['empty', 'invalid'], true)) {
                $skipped[] = $pdf->getClientOriginalName();
                continue;
            }

            $pattern = $this->library->createPdfDraft($pdf, $request->user()?->id);
            ExtractPatternFromPdfJob::dispatch($pattern->id);
            $imported++;
        }

        $parts = [];
        if ($imported > 0) { $parts[] = __('patternengine::messages.pattern.pdf_queued_part', ['count' => $imported]); }
        if (! empty($skipped)) { $parts[] = __('patternengine::messages.pattern.skipped_part', ['files' => implode(', ', $skipped)]); }

        if ($imported === 0) {
            return back()->with('error', __('patternengine::messages.pattern.no_pdf_processed', ['details' => implode('; ', $parts)]));
        }

        return redirect()->route('atelier.patterns.index')->with('success', implode('; ', $parts) . '.');
    }

    /**
     * Toplu kalıp FOTOĞRAFI yükle → her biri için 'inceleniyor' taslak kalıp + arka
     * plan çıkarımı (bkz. python/pdf_dxf_service/photo_vectorize.py). importPdf ile
     * aynı akış; ön-kontrol yok (fotoğraf her zaman kuyruğa girer, marker'sız çekimler
     * ExtractPatternFromPhotoJob içinde 'failed' + operatöre yeniden çekim talimatıyla döner).
     */
    public function importPhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'files'     => ['required', 'array', 'min:1'],
            'files.*'   => ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:20480'],
            'marker_mm' => ['nullable', 'numeric', 'min:5', 'max:500'],
        ]);

        $markerMm = (float) $request->input('marker_mm', 40.0);

        foreach ($request->file('files') as $photo) {
            $pattern = $this->library->createPhotoDraft($photo, $request->user()?->id);
            ExtractPatternFromPhotoJob::dispatch($pattern->id, $markerMm);
        }

        $count = count($request->file('files'));

        return redirect()->route('atelier.patterns.index')
            ->with('success', __('patternengine::messages.pattern.photos_queued', ['count' => $count]));
    }

    /**
     * Sabit-domain tedarikçi sayfasından (bkz. config/atelier.php pattern_import)
     * PDF + önizleme görselini otomatik bulup indirir, importPdf ile AYNI akışla
     * ('inceleniyor' taslak + arka plan çıkarımı) kütüphaneye ekler.
     */
    public function importFromUrl(ImportPatternFromUrlRequest $request): RedirectResponse
    {
        try {
            $this->urlImporter->importFromUrl($request->validated('url'), $request->user()?->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['url' => __('patternengine::messages.pattern.import_failed', ['error' => $e->getMessage()])]);
        }

        return redirect()->route('atelier.patterns.index')->with('success', __('patternengine::messages.pattern.imported_from_link'));
    }

    /**
     * Çıkarımı başarısız olan taslağı, saklı PDF'i kullanarak yeniden kuyruğa alır.
     */
    public function retryExtraction(Pattern $pattern): RedirectResponse
    {
        if ($pattern->extraction_status !== Pattern::EXTRACTION_FAILED || ! $pattern->pdf_path) {
            return back()->withErrors(['extraction' => __('patternengine::messages.pattern.only_failed_retry')]);
        }

        $pattern->update([
            'extraction_status' => Pattern::EXTRACTION_PROCESSING,
            'extraction_error'  => null,
        ]);
        ExtractPatternFromPdfJob::dispatch($pattern->id);

        return redirect()->route('atelier.patterns.index')->with('success', __('patternengine::messages.pattern.extraction_restarted'));
    }

    /**
     * Raster taslak için insan-destekli izleme editörünü açar.
     */
    public function tracer(Pattern $pattern): Response
    {
        abort_unless(
            $pattern->pdf_path && $pattern->extraction_status === Pattern::EXTRACTION_NEEDS_TRACING,
            404,
        );

        return Inertia::render('PatternEngine::RasterTracer', [
            'pattern' => [
                'id'          => $pattern->id,
                'name'        => $pattern->name,
                'productType' => $pattern->product_type,
                'sizeRange'   => $pattern->size_range,
            ],
            'imageBase' => route('atelier.patterns.tracer-image', ['pattern' => $pattern->id]),
        ]);
    }

    /**
     * Taranmış PDF sayfasını PNG olarak render eder (tuval backdrop'u).
     */
    public function tracerImage(Request $request, Pattern $pattern): HttpResponse
    {
        abort_unless((bool) $pattern->pdf_path, 404);
        $page = max(0, (int) $request->query('page', 0));
        $dpi  = min(300, max(72, (int) $request->query('dpi', 200)));
        $abs  = Media::localPath($pattern->pdf_path);
        abort_unless((bool) $abs, 404);

        try {
            $png = $this->converter->renderPage($abs, $page, $dpi);
        } catch (\Throwable $e) {
            abort(502, __('patternengine::messages.pattern.page_render_failed', ['error' => $e->getMessage()]));
        }

        return response($png, 200)->header('Content-Type', 'image/png');
    }

    /**
     * İzleme çıktısını (px poligonları) mm'ye çevirir, DXF üretir, kütüphaneye yazar.
     */
    public function saveTraced(SaveTracedPatternRequest $request, Pattern $pattern): RedirectResponse
    {
        abort_unless($pattern->extraction_status === Pattern::EXTRACTION_NEEDS_TRACING, 404);
        $v   = $request->validated();
        $ppm = (float) $v['calibration']['px_per_mm'];
        $h   = (int) $v['calibration']['image_height_px'];

        $polylines = [];
        $parts     = [];
        foreach ($v['pieces'] as $piece) {
            foreach ($piece['polylines'] as $pl) {
                $mm = [];
                foreach ($pl['points'] as $pt) {
                    $mm[] = [round(((float) $pt[0]) / $ppm, 3), round(($h - (float) $pt[1]) / $ppm, 3)];
                }
                $polylines[] = ['role' => $pl['role'], 'points' => $mm, 'closed' => $pl['role'] === 'cut'];
            }
            $parts[] = [
                'part_name'  => $piece['name'],
                'quantity'   => $piece['quantity'] ?? 1,
                'size_range' => $piece['size'] ?? null,
            ];
        }

        try {
            $dxf = $this->converter->buildDxf($polylines);
        } catch (\Throwable $e) {
            return back()->withErrors(['trace' => __('patternengine::messages.pattern.dxf_failed', ['error' => $e->getMessage()])]);
        }

        $this->library->applyTracedDxf($pattern, $dxf, [
            'name'         => $v['name'],
            'product_type' => $v['product_type'] ?? $pattern->product_type,
            'size_range'   => $v['size_range'] ?? null,
        ], $parts);

        return redirect()->route('atelier.patterns.index')->with('success', __('patternengine::messages.pattern.digitized'));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $parts, $tags, $files] = $this->validatePattern($request);

        $this->library->create($data, $files, $parts, $tags, $request->user()?->id);

        return redirect()->route('atelier.patterns.index')->with('success', __('patternengine::messages.pattern.created'));
    }

    public function update(Request $request, Pattern $pattern): RedirectResponse
    {
        [$data, $parts, $tags, $files] = $this->validatePattern($request, $pattern->id);

        $this->library->update($pattern, $data, $files, $parts, $tags);

        return redirect()->route('atelier.patterns.index')->with('success', __('patternengine::messages.pattern.updated'));
    }

    public function destroy(Pattern $pattern): RedirectResponse
    {
        $this->library->delete($pattern);

        return redirect()->route('atelier.patterns.index')->with('success', __('patternengine::messages.pattern.deleted'));
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>,2:array<int,string>,3:array<string,mixed>}
     */
    private function validatePattern(Request $request, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'code'         => ['nullable', 'string', 'max:40', Rule::unique('patterns', 'code')->ignore($ignoreId)->whereNull('deleted_at')],
            'name'         => ['required', 'string', 'max:191'],
            'product_type' => ['required', 'string', 'max:100'],
            'size_range'   => ['nullable', 'string', 'max:100'],
            'vendor'       => ['nullable', 'string', 'max:191'],
            'collection'   => ['nullable', 'string', 'max:191'],
            'status'       => ['required', Rule::in([Pattern::STATUS_DRAFT, Pattern::STATUS_APPROVED, Pattern::STATUS_REJECTED])],
            'scale_verified'     => ['boolean'],
            'scale_deviation_mm' => ['nullable', 'numeric', 'between:-9999.99,9999.99'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'parts'                => ['array'],
            // Boş satırlar serviste atlanır; burada zorunlu kılmıyoruz.
            'parts.*.part_name'    => ['nullable', 'string', 'max:100'],
            'parts.*.quantity'     => ['nullable', 'integer', 'min:1'],
            'parts.*.size_range'   => ['nullable', 'string', 'max:100'],
            'parts.*.part_no'      => ['nullable', 'string', 'max:16'],
            'parts.*.variants'     => ['nullable', 'string', 'max:50'],
            'parts.*.fabric_code'  => ['nullable', 'string', 'max:16'],
            'parts.*.notes'        => ['nullable', 'string', 'max:191'],
            'tags'         => ['array'],
            'tags.*'       => ['string', 'max:50'],
            'preview_image'=> ['nullable', 'image', 'max:5120'],
            'dxf'          => ['nullable', 'file', 'mimetypes:application/dxf,image/vnd.dxf,application/octet-stream,text/plain', 'max:20480'],
            'pdf'          => ['nullable', 'file', 'mimetypes:application/pdf', 'max:51200'],
        ]);

        $data = collect($validated)->only([
            'code', 'name', 'product_type', 'size_range', 'vendor', 'collection',
            'status', 'scale_verified', 'scale_deviation_mm', 'notes',
        ])->all();
        $data['scale_verified'] = (bool) ($validated['scale_verified'] ?? false);

        return [
            $data,
            $validated['parts'] ?? [],
            $validated['tags'] ?? [],
            [
                'preview_image' => $request->file('preview_image'),
                'dxf'           => $request->file('dxf'),
                'pdf'           => $request->file('pdf'),
            ],
        ];
    }
}
