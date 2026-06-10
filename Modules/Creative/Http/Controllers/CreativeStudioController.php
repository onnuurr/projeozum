<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\GenerateCreativesRequest;
use Modules\Creative\Jobs\GenerateCreativeJob;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Product\Models\Product;

class CreativeStudioController extends Controller
{
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

        return Inertia::render('Creative::CreativeStudio', [
            'templates' => $templates,
            'products'  => $products,
        ]);
    }

    public function generate(GenerateCreativesRequest $request): RedirectResponse
    {
        $templateId = (int) $request->validated('template_id');
        $productIds = $request->validated('product_ids');
        $useAi      = (bool) $request->validated('use_ai', false);

        foreach ($productIds as $productId) {
            $asset = CreativeAsset::create([
                'product_id'    => (int) $productId,
                'template_id'   => $templateId,
                'status'        => CreativeAsset::STATUS_QUEUED,
                'review_status' => CreativeAsset::REVIEW_PENDING,
                'meta'          => ['use_ai' => $useAi],
            ]);

            GenerateCreativeJob::dispatch($asset->id);
        }

        return redirect()->route('creative.gallery')
            ->with('success', count($productIds) . ' görsel üretim kuyruğuna alındı.');
    }

    public function gallery(): Response
    {
        $assets = CreativeAsset::query()
            ->with(['product:id,name,slug', 'template:id,name'])
            ->latest()
            ->paginate(40)
            ->through(fn (CreativeAsset $a) => [
                'id'            => $a->id,
                'status'        => $a->status,
                'review_status' => $a->review_status,
                'error'         => $a->error,
                'image_url'     => $this->url($a->image_path),
                'caption'       => $a->meta['caption'] ?? null,
                'hashtags'      => $a->meta['hashtags'] ?? [],
                'product'       => $a->product?->only(['id', 'name', 'slug']),
                'template'      => $a->template?->only(['id', 'name']),
                'created_at'    => $a->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Creative::CreativeGallery', [
            'assets' => $assets,
        ]);
    }

    public function approve(CreativeAsset $asset): RedirectResponse
    {
        $asset->update(['review_status' => CreativeAsset::REVIEW_APPROVED]);

        return back()->with('success', 'Görsel onaylandı.');
    }

    public function reject(CreativeAsset $asset): RedirectResponse
    {
        $asset->update(['review_status' => CreativeAsset::REVIEW_REJECTED]);

        return back()->with('success', 'Görsel reddedildi.');
    }

    public function regenerate(CreativeAsset $asset): RedirectResponse
    {
        $asset->update([
            'status'        => CreativeAsset::STATUS_QUEUED,
            'review_status' => CreativeAsset::REVIEW_PENDING,
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
