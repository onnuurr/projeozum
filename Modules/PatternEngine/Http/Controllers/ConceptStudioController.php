<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Jobs\GenerateConceptJob;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Concept\ConceptRequest;
use Modules\Atelier\Services\Concept\ConceptStudioService;

class ConceptStudioController extends Controller
{
    public function __construct(private ConceptStudioService $studio) {}

    public function index(): Response
    {
        $disk = (string) config('atelier.concept.disk', 'public');

        $cards = DesignCard::query()
            ->with('pattern:id,name,product_type')
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (DesignCard $c) => [
                'id'          => $c->id,
                'source'      => $c->source,
                'prompt'      => $c->prompt,
                'productType' => $c->product_type,
                'targetSize'  => $c->target_size,
                'status'      => $c->status,
                'generationStatus' => $c->generation_status,
                'generationError'  => $c->generation_error,
                'images'      => collect($c->generated_images ?? [])
                    ->map(fn (string $p) => Storage::disk($disk)->url($p))
                    ->all(),
                'pattern'     => $c->pattern ? [
                    'id' => $c->pattern->id, 'name' => $c->pattern->name, 'productType' => $c->pattern->product_type,
                ] : null,
                'createdAt'   => $c->created_at?->format('Y-m-d H:i'),
            ]);

        // Akış X (önce kalıp) ve Akış Y eşlemesi için onaylı kalıplar.
        $patterns = Pattern::query()
            ->where('status', Pattern::STATUS_APPROVED)
            ->orderBy('name')
            ->get(['id', 'name', 'product_type', 'size_range'])
            ->map(fn (Pattern $p) => [
                'id' => $p->id, 'name' => $p->name,
                'productType' => $p->product_type, 'sizeRange' => $p->size_range,
            ]);

        return Inertia::render('Atelier::ConceptStudio', [
            'cards'       => $cards,
            'patterns'    => $patterns,
            'maxVariants' => (int) config('atelier.concept.max_variants', 4),
            'driver'      => config('atelier.concept.driver') === 'gemini' && config('creative.ai.gemini.api_key')
                ? 'gemini' : 'mock',
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $max  = (int) config('atelier.concept.max_variants', 4);
        $data = $request->validate([
            'product_type' => ['required', 'string', 'max:100'],
            'source'       => ['required', Rule::in([DesignCard::SOURCE_CONCEPT_FIRST, DesignCard::SOURCE_PATTERN_FIRST])],
            'pattern_id'   => [
                Rule::requiredIf($request->input('source') === DesignCard::SOURCE_PATTERN_FIRST),
                'nullable', 'integer', Rule::exists('patterns', 'id'),
            ],
            'description'  => ['nullable', 'string', 'max:1000'],
            'palette'      => ['nullable', 'string', 'max:191'],
            'motif'        => ['nullable', 'string', 'max:191'],
            'style'        => ['nullable', 'string', 'max:191'],
            'size_range'   => ['nullable', 'string', 'max:100'],
            'count'        => ['required', 'integer', 'min:1', "max:{$max}"],
        ]);

        try {
            $card = $this->studio->create(new ConceptRequest(
                productType: $data['product_type'],
                description: $data['description'] ?? null,
                palette: $data['palette'] ?? null,
                motif: $data['motif'] ?? null,
                style: $data['style'] ?? null,
                sizeRange: $data['size_range'] ?? null,
                count: (int) $data['count'],
                source: $data['source'],
                patternId: $data['pattern_id'] ?? null,
            ), createdBy: $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['pattern_id' => $e->getMessage()]);
        }

        GenerateConceptJob::dispatch($card->id);

        return redirect()->route('atelier.concepts.index')->with('success', 'Konsept üretimi başlatıldı.');
    }

    public function regenerate(DesignCard $card): RedirectResponse
    {
        if ($card->generation_status !== DesignCard::GENERATION_FAILED || empty($card->request_params)) {
            return back()->withErrors(['generation' => 'Yalnızca üretimi başarısız konseptler yeniden denenebilir.']);
        }

        $card->update([
            'generation_status' => DesignCard::GENERATION_PROCESSING,
            'generation_error'  => null,
        ]);
        GenerateConceptJob::dispatch($card->id);

        return redirect()->route('atelier.concepts.index')->with('success', 'Üretim yeniden başlatıldı.');
    }

    public function match(Request $request, DesignCard $card): RedirectResponse
    {
        $data = $request->validate([
            'pattern_id' => ['required', 'integer', Rule::exists('patterns', 'id')],
        ]);

        $this->studio->matchPattern($card, Pattern::findOrFail($data['pattern_id']));

        return redirect()->route('atelier.concepts.index')->with('success', 'Konsept kalıba eşlendi.');
    }

    public function destroy(DesignCard $card): RedirectResponse
    {
        $card->update(['status' => DesignCard::STATUS_ARCHIVED]);
        $card->delete();

        return redirect()->route('atelier.concepts.index')->with('success', 'Konsept arşivlendi.');
    }
}
