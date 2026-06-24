<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Jobs\ProcessConversionJob;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Services\Conversion\ConversionPipelineService;

class ConversionController extends Controller
{
    public function __construct(private ConversionPipelineService $pipeline) {}

    public function index(Request $request): Response
    {
        $disk   = (string) config('atelier.conversion.disk', 'public');
        $status = (string) $request->input('status', '');

        $jobs = ConversionJob::query()
            ->with('pattern:id,name')
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->limit(150)
            ->get()
            ->map(fn (ConversionJob $j) => [
                'id'             => $j->id,
                'fileName'       => basename((string) $j->source_pdf_path),
                'status'         => $j->status,
                'classification' => $j->classification,
                'confidence'     => $j->confidence_score !== null ? (float) $j->confidence_score : null,
                'pdfUrl'         => $j->source_pdf_path ? Storage::disk($disk)->url($j->source_pdf_path) : null,
                'dxfUrl'         => $j->output_dxf_path ? Storage::disk($disk)->url($j->output_dxf_path) : null,
                'metadata'       => $j->error_report['metadata'] ?? null,
                'errors'         => $j->error_report['errors'] ?? [],
                'pattern'        => $j->pattern ? ['id' => $j->pattern->id, 'name' => $j->pattern->name] : null,
                'reviewedAt'     => optional($j->reviewed_at)->format('Y-m-d H:i'),
                'createdAt'      => $j->created_at?->format('Y-m-d H:i'),
            ]);

        // Triyaj sayaçları (operatör kuyruğu önceliklendirmesi için).
        $counts = ConversionJob::query()
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Atelier::Conversions', [
            'jobs'      => $jobs,
            'filters'   => ['status' => $status],
            'counts'    => $counts,
            'driver'    => config('atelier.conversion.driver') === 'http' && config('atelier.conversion.service_url')
                ? 'http' : 'mock',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimetypes:application/pdf', 'max:51200'],
        ]);

        foreach ($request->file('files') as $pdf) {
            $job = $this->pipeline->submit($pdf, $request->user()?->id);
            ProcessConversionJob::dispatch($job->id);
        }

        $n = count($request->file('files'));

        return redirect()->route('atelier.conversions.index')
            ->with('success', "{$n} PDF dönüştürme kuyruğuna alındı.");
    }

    public function approve(Request $request, ConversionJob $job): RedirectResponse
    {
        if (! in_array($job->status, [ConversionJob::STATUS_NEEDS_REVIEW, ConversionJob::STATUS_FAILED], true)) {
            return back()->withErrors(['status' => 'Yalnızca incelemedeki işler onaylanabilir.']);
        }
        if (! $job->output_dxf_path) {
            return back()->withErrors(['status' => 'DXF üretilemediği için onaylanamaz; reddedin veya yeniden işleyin.']);
        }

        $this->pipeline->approve($job, $request->user()?->id);

        return redirect()->route('atelier.conversions.index')->with('success', 'Kalıp kütüphaneye eklendi.');
    }

    public function reject(Request $request, ConversionJob $job): RedirectResponse
    {
        $this->pipeline->reject($job, $request->user()?->id);

        return redirect()->route('atelier.conversions.index')->with('success', 'İş reddedildi.');
    }

    public function retry(ConversionJob $job): RedirectResponse
    {
        $job->update(['status' => ConversionJob::STATUS_PENDING]);
        ProcessConversionJob::dispatch($job->id);

        return redirect()->route('atelier.conversions.index')->with('success', 'İş yeniden kuyruğa alındı.');
    }
}
