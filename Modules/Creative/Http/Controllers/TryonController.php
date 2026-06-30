<?php

namespace Modules\Creative\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Creative\Http\Requests\GenerateTryonRequest;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\ProductOnModelService;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;

class TryonController extends Controller
{
    public function index(): Response
    {
        // Giysi kaynağı ProductOnModelService::pickGarmentSrc ile aynı mantıkta seçilir:
        // önce kapak, yoksa ilk görsel. Görseli olmayan ürün giydirilemez (has_garment=false).
        $products = Product::query()
            ->with(['images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order')])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Product $p) {
                $garment = $p->images->first();

                return [
                    'id'          => $p->id,
                    'name'        => $p->name,
                    'cover'       => $garment?->url,
                    'has_garment' => $garment !== null,
                ];
            });

        // Kimliği hazır + referans görseli olan mankenler giydirmeye uygundur.
        $mannequins = Mannequin::query()
            ->where('status', Mannequin::STATUS_READY)
            ->whereNotNull('reference_image_path')
            ->orderBy('name')
            ->get()
            ->map(fn (Mannequin $m) => [
                'id'            => $m->id,
                'name'          => $m->name,
                'reference_url' => $this->url($m->reference_image_path),
            ]);

        // Bağımsız poz kütüphanesinden hazır pozlar.
        $poses = Pose::query()
            ->where('status', Pose::STATUS_READY)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Pose $p) => [
                'id'          => $p->id,
                'label'       => $p->label,
                'preview_url' => $this->url($p->preview_image_path),
            ]);

        $results = TryonResult::query()
            ->with(['product:id,name', 'mannequin:id,name', 'pose:id,label', 'productImage:id,url,is_cover'])
            ->latest()
            ->limit(60)
            ->get()
            ->map(fn (TryonResult $r) => [
                'id'             => $r->id,
                'status'         => $r->status,
                'error'          => $r->error,
                'product_name'   => $r->product?->name,
                'mannequin_name' => $r->mannequin?->name,
                'pose_label'     => $r->pose?->label,
                'image_url'      => $r->productImage?->url,
                'is_cover'       => (bool) $r->productImage?->is_cover,
                'created_at'     => $r->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Creative::CreativeTryon', [
            'products'   => $products,
            'mannequins' => $mannequins,
            'poses'      => $poses,
            'results'    => $results,
        ]);
    }

    public function store(GenerateTryonRequest $request, ProductOnModelService $service): RedirectResponse
    {
        $product   = Product::with('images')->findOrFail($request->validated('product_id'));
        $mannequin = Mannequin::findOrFail($request->validated('mannequin_id'));

        // Giydirme, ürünün fotoğrafını giysi olarak kullanır; fotoğrafı olmayan ürün
        // giydirilemez. Kuyruğa almadan, kullanıcıya net hata döndür.
        $garment = $product->images->firstWhere('is_cover', true) ?? $product->images->first();
        if (! $garment) {
            throw ValidationException::withMessages([
                'product_id' => 'Bu ürünün giydirilecek bir fotoğrafı yok. Önce ürüne fotoğraf ekleyin.',
            ]);
        }

        $queued = $service->queue($product, $mannequin, $request->validated('pose_ids'));

        if ($queued->isEmpty()) {
            return back()->with('error', 'Seçili pozların hiçbiri hazır değil.');
        }

        foreach ($queued as $result) {
            GenerateOnModelJob::dispatch($result->id);
        }

        return back()->with('success', $queued->count() . ' görsel giydirme kuyruğuna alındı.');
    }

    /**
     * Giydirme çıktısını (üretilen ürün görselini) ürünün kapağı yapar.
     */
    public function setCover(TryonResult $result): RedirectResponse
    {
        $result->loadMissing('productImage');
        $image = $result->productImage;

        if ($result->status !== TryonResult::STATUS_DONE || ! $image) {
            return back()->with('error', 'Bu sonuç henüz kapak yapılamaz.');
        }

        DB::transaction(function () use ($image) {
            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->where('id', '!=', $image->id)
                ->update(['is_cover' => false]);

            $image->update(['is_cover' => true]);
        });

        return back()->with('success', 'Ürün kapağı güncellendi.');
    }

    /**
     * Giydirme sonucunu ve ürettiği ürün görselini (dosyasıyla) siler.
     */
    public function destroyResult(TryonResult $result): RedirectResponse
    {
        $result->loadMissing('productImage');
        $this->deleteProductImage($result->productImage);
        $result->delete();

        return back()->with('success', 'Giydirme sonucu silindi.');
    }

    private function deleteProductImage(?ProductImage $image): void
    {
        if (! $image) {
            return;
        }

        // DB'de relative path tutulur; aktif medya diskinden doğrudan silinir.
        if ($image->path) {
            Storage::disk(Media::disk())->delete($image->path);
        }

        $image->delete();
    }

    private function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Storage::disk(config('creative.disk', 'public'))->url($path);
    }
}
