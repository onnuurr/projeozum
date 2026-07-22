<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Controllers\Concerns\HandlesCreativeReview;
use Modules\Creative\Http\Requests\GenerateCreativesRequest;
use Modules\Creative\Jobs\GenerateCreativeJob;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\ReviewNotifier;
use Modules\Product\Models\Product;

class CreativeStudioController extends Controller
{
    use HandlesCreativeReview;

    public function index(): Response
    {
        $templates = CreativeTemplate::query()
            ->where('is_active', true)
            ->latest()
            ->get(['id', 'name', 'width', 'height', 'svg_path', 'thumbnail_path', 'slots'])
            ->map(fn (CreativeTemplate $t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'width'       => $t->width,
                'height'      => $t->height,
                'slots'       => $t->slots,
                'preview_url' => $this->url($t->thumbnail_path ?: $t->svg_path),
            ]);

        $products = Product::query()
            ->with(['images' => fn ($q) => $q->where('is_cover', true)->orderBy('sort_order')])
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn (Product $p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'cover' => optional($p->images->first())->url,
            ]);

        $formats = collect(config('creative.formats', []))
            ->map(fn ($f, $key) => [
                'key'    => $key,
                'label'  => $f['label'] ?? $key,
                'width'  => $f['width'],
                'height' => $f['height'],
            ])->values();

        return Inertia::render('Creative::CreativeStudio', [
            'templates'      => $templates,
            'products'       => $products,
            'formats'        => $formats,
            'default_format' => config('creative.default_format'),
            // ai_compose toggle'ı yalnızca gerçek bir fal sürücüsü + OCR
            // doğrulaması yapılandırılmışsa gösterilir — mock'ken sessizce
            // gizli kalır, yanıltıcı bir "özellik" vaadi vermez (bkz. Faz J).
            'ai_compose_available' => config('creative.composition.driver') === 'fal'
                && (bool) config('creative.ai.fal.key')
                && (bool) config('creative.composition.ocr.enabled'),
        ]);
    }

    public function generate(GenerateCreativesRequest $request): RedirectResponse
    {
        $templateId   = (int) $request->validated('template_id');
        $productIds   = $request->validated('product_ids');
        $useAi        = (bool) $request->validated('use_ai', false);
        $useCopyAi    = (bool) $request->validated('use_copy_ai', false);
        $renderEngine = $request->validated('render_engine', 'svg');
        $format       = $request->validated('format', config('creative.default_format'));
        $pose         = trim((string) $request->validated('pose', ''));

        $meta = ['use_ai' => $useAi, 'use_copy_ai' => $useCopyAi, 'render_engine' => $renderEngine, 'format' => $format];
        if ($pose !== '') {
            // Boşsa hiç yazma; prompt builder ürüne göre kürate poz seçsin.
            $meta['pose'] = $pose;
        }

        foreach ($productIds as $productId) {
            $asset = CreativeAsset::create([
                'product_id'    => (int) $productId,
                'template_id'   => $templateId,
                'created_by'    => auth()->id(),
                'status'        => CreativeAsset::STATUS_QUEUED,
                'review_status' => CreativeAsset::REVIEW_PENDING,
                'meta'          => $meta,
            ]);

            GenerateCreativeJob::dispatch($asset->id);
        }

        return redirect()->route('creative.gallery')
            ->with('success', count($productIds) . ' görsel üretim kuyruğuna alındı.');
    }

    public function gallery(): Response
    {
        $assets = CreativeAsset::query()
            ->with([
                'product:id,name,slug', 'template:id,name', 'creator:id,name', 'reviewer:id,name',
                'reviewChats' => fn ($q) => $q->orderBy('created_at')->with('user:id,name'),
            ])
            ->latest()
            ->paginate(40)
            ->through(fn (CreativeAsset $a) => [
                'id'              => $a->id,
                'status'          => $a->status,
                'review_status'   => $a->review_status,
                'error'           => $a->error,
                'image_url'       => $this->url($a->image_path),
                'caption'         => $a->meta['caption'] ?? null,
                'hashtags'        => $a->meta['hashtags'] ?? [],
                'format_label'    => $a->meta['format_label'] ?? null,
                'width'           => $a->meta['width'] ?? null,
                'height'          => $a->meta['height'] ?? null,
                'product'         => $a->product?->only(['id', 'name', 'slug']),
                'template'        => $a->template?->only(['id', 'name']),
                'created_at'      => $a->created_at?->toDateTimeString(),
                'created_by'      => $a->created_by,
                'creator_name'    => $a->creator?->name,
                'review_note'     => $a->review_note,
                'review_tags'     => $a->review_tags ?? [],
                'reviewer_name'   => $a->reviewer?->name,
                'can_review'      => auth()->user()?->can('creative.approve')
                    && $a->created_by !== auth()->id()
                    && $a->review_status === CreativeAsset::REVIEW_PENDING,
                'is_own'          => $a->created_by === auth()->id(),
                'can_chat'        => $a->review_status === CreativeAsset::REVIEW_REJECTED
                    && ($a->created_by === auth()->id() || auth()->user()?->can('creative.approve')),
                'review_chats'    => $a->reviewChats->map(fn ($c) => [
                    'id'         => $c->id,
                    'role'       => $c->role,
                    'content'    => $c->content,
                    'user_name'  => $c->user?->name,
                    'created_at' => $c->created_at?->toDateTimeString(),
                ]),
                'chat_suggestion' => $a->meta['chat_suggested_instruction'] ?? null,
            ]);

        return Inertia::render('Creative::CreativeGallery', [
            'assets'           => $assets,
            'stats'            => $this->stats(),
            'rejectionReasons' => $this->rejectionReasonGroups('gallery'),
        ]);
    }

    /**
     * Galeri üst barı için üretim istatistikleri (Postgres FILTER + JSON).
     *
     * @return array<string,mixed>
     */
    private function stats(): array
    {
        $totals = CreativeAsset::query()->selectRaw("
            count(*) as total,
            count(*) filter (where status = 'done') as done,
            count(*) filter (where status = 'failed') as failed,
            count(*) filter (where status in ('queued','processing')) as pending,
            count(*) filter (where review_status = 'approved') as approved,
            avg((meta->>'render_ms')::numeric) filter (where meta->>'render_ms' is not null) as avg_render_ms
        ")->first();

        $done   = (int) ($totals->done ?? 0);
        $failed = (int) ($totals->failed ?? 0);
        $finished = $done + $failed;

        $perTemplate = CreativeAsset::query()
            ->join('creative_templates', 'creative_templates.id', '=', 'creative_assets.template_id')
            ->groupBy('creative_templates.id', 'creative_templates.name')
            ->selectRaw("creative_templates.name, count(*) as total, count(*) filter (where creative_assets.status = 'done') as done")
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'name'  => $r->name,
                'total' => (int) $r->total,
                'done'  => (int) $r->done,
            ]);

        return [
            'total'         => (int) ($totals->total ?? 0),
            'done'          => $done,
            'failed'        => $failed,
            'pending'       => (int) ($totals->pending ?? 0),
            'approved'      => (int) ($totals->approved ?? 0),
            'success_rate'  => $finished > 0 ? (int) round($done / $finished * 100) : null,
            'avg_render_ms' => $totals->avg_render_ms !== null ? (int) round($totals->avg_render_ms) : null,
            'per_template'  => $perTemplate,
        ];
    }

    /**
     * Onaylanmış (approved + done) görselleri ZIP olarak indirir; caption varsa
     * her görselin yanına .txt olarak ekler. Harici servis kullanmaz.
     */
    public function export()
    {
        $assets = CreativeAsset::query()
            ->with('product:id,name,slug')
            ->where('review_status', CreativeAsset::REVIEW_APPROVED)
            ->where('status', CreativeAsset::STATUS_DONE)
            ->whereNotNull('image_path')
            ->get();

        if ($assets->isEmpty()) {
            return back()->with('warning', 'İndirilecek onaylı görsel yok.');
        }

        $disk = Storage::disk(config('creative.disk', 'public'));
        $tmp  = tempnam(sys_get_temp_dir(), 'creative-zip-');

        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'ZIP oluşturulamadı.');
        }

        foreach ($assets as $asset) {
            if (! $disk->exists($asset->image_path)) {
                continue;
            }

            $slug  = $asset->product?->slug ?: 'asset';
            $base  = sprintf('%s-%d', $slug, $asset->id);

            // addFromString disk-bağımsızdır (yerel ve R2/S3 için aynı çalışır).
            $zip->addFromString($base . '.png', (string) $disk->get($asset->image_path));

            $caption  = $asset->meta['caption'] ?? null;
            $hashtags = $asset->meta['hashtags'] ?? [];
            if ($caption || ! empty($hashtags)) {
                $zip->addFromString(
                    $base . '.txt',
                    trim(($caption ?? '') . "\n\n" . implode(' ', (array) $hashtags)),
                );
            }
        }

        $zip->close();

        return response()
            ->download($tmp, 'creative-export-' . now()->format('Ymd-His') . '.zip')
            ->deleteFileAfterSend(true);
    }

    public function approve(CreativeAsset $asset, ReviewNotifier $notifier): RedirectResponse
    {
        $this->guardNotOwnWork($asset);

        if ($asset->review_status !== CreativeAsset::REVIEW_PENDING) {
            return back()->with('error', 'Bu görsel onay bekliyor durumda değil.');
        }

        $asset->update([
            'review_status' => CreativeAsset::REVIEW_APPROVED,
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        $notifier->notifyDecision($asset, true);

        return back()->with('success', 'Görsel onaylandı.');
    }

    public function reject(CreativeAsset $asset, Request $request, ReviewNotifier $notifier): RedirectResponse
    {
        $this->guardNotOwnWork($asset);

        if ($asset->review_status !== CreativeAsset::REVIEW_PENDING) {
            return back()->with('error', 'Bu görsel onay bekliyor durumda değil.');
        }

        $review = $this->validatedReview($request);

        $asset->update([
            'review_status' => CreativeAsset::REVIEW_REJECTED,
            'review_note'   => $review['note'],
            'review_tags'   => $review['tags'],
            'reviewed_by'   => auth()->id(),
            'reviewed_at'   => now(),
        ]);

        $notifier->notifyDecision($asset, false);

        return back()->with('success', 'Görsel reddedildi.');
    }

    public function regenerate(CreativeAsset $asset): RedirectResponse
    {
        $asset->update([
            'status'        => CreativeAsset::STATUS_QUEUED,
            'review_status' => CreativeAsset::REVIEW_PENDING,
            'review_note'   => null,
            'review_tags'   => null,
            'reviewed_by'   => null,
            'reviewed_at'   => null,
            'error'         => null,
        ]);

        GenerateCreativeJob::dispatch($asset->id);

        return back()->with('success', 'Görsel yeniden üretim kuyruğuna alındı.');
    }

    public function updateCaption(CreativeAsset $asset): RedirectResponse
    {
        $data = request()->validate([
            'caption'    => ['nullable', 'string', 'max:2200'],
            'hashtags'   => ['nullable', 'array', 'max:30'],
            'hashtags.*' => ['string', 'max:60'],
        ]);

        $asset->update([
            'meta' => array_merge($asset->meta ?? [], [
                'caption'  => $data['caption'] ?? null,
                'hashtags' => array_values($data['hashtags'] ?? []),
            ]),
        ]);

        return back()->with('success', 'Caption güncellendi.');
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }
}
