<?php

namespace Modules\Creative\Services;

use App\Support\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\MannequinPoseComposerContract;
use Modules\Creative\Services\Ai\MannequinPoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use RuntimeException;

/**
 * Ürün giydirme orchestrator'ı (asıl çıktı).
 *
 * İki AI adımı: 1) seçilen mankeni, seçilen pozun yönergesiyle compose et
 * (kimlik referansı korunur), 2) ürünü bu poza idm-vton ile giydir. Sonuç
 * Product modülünün product_images tablosuna yazılır (ürün seviyesi).
 * İzlenebilirlik/idempotensi: creative_tryon_results (product_id+pose_id benzersiz).
 */
class ProductOnModelService
{
    /** Üretilen ürün görsellerinin saklandığı disk (Product modülü konvansiyonu). */

    public function __construct(
        private MannequinPoseComposerContract $poseComposer,
        private GarmentTryOnContract $tryOn,
        private CanvasAssetResolver $resolver,
        private ImageEnhancerContract $enhancer,
    ) {}

    /**
     * Ürün + manken + seçili HAZIR pozlar için tryon_result satırları hazırlar.
     *
     * @param  array<int,int>  $poseIds  Bağımsız poz kütüphanesi id'leri
     * @return Collection<int,TryonResult>
     */
    public function queue(Product $product, Mannequin $mannequin, array $poseIds): Collection
    {
        $poses = Pose::query()
            ->where('status', Pose::STATUS_READY)
            ->whereIn('id', $poseIds)
            ->get();

        return $poses->map(fn (Pose $pose) => TryonResult::updateOrCreate(
            ['product_id' => $product->id, 'pose_id' => $pose->id],
            [
                'mannequin_id' => $mannequin->id,
                'status'       => TryonResult::STATUS_QUEUED,
                'error'        => null,
            ],
        ));
    }

    /**
     * Tek bir giydirme sonucunu üretir: mankeni pozla → ürünü giydir → product_images.
     */
    public function generate(TryonResult $result): TryonResult
    {
        $product   = $result->product()->with('images')->first();
        $mannequin = $result->mannequin;
        $pose      = $result->pose;

        if (! $product) {
            throw new RuntimeException('Giydirme için ürün bulunamadı.');
        }
        if (! $mannequin || $mannequin->status !== Mannequin::STATUS_READY || ! $mannequin->reference_image_path) {
            throw new RuntimeException('Manken hazır değil; önce manken kimlik görseli üretilmeli.');
        }
        if (! $pose || $pose->status !== Pose::STATUS_READY) {
            throw new RuntimeException('Seçili poz hazır değil.');
        }

        $refPath = Media::localPath($mannequin->reference_image_path);
        if (! $refPath) {
            throw new RuntimeException('Manken referans görseli diskte bulunamadı.');
        }

        $garmentSrc = $this->pickGarmentSrc($product);
        if (! $garmentSrc) {
            throw new RuntimeException('Ürünün giydirilecek bir görseli yok.');
        }
        $garmentPath = $this->resolver->toLocalPath($garmentSrc);
        if (! $garmentPath) {
            throw new RuntimeException('Ürün görseli yerel yola çözülemedi.');
        }

        $oldImageId = $result->product_image_id;
        $posed      = null;
        $out        = null;
        $enhanced   = null;

        // Pozun önizleme görseli birebir duruş referansı olarak kullanılır (varsa).
        $posePreview = null;
        if ($pose->preview_image_path) {
            $posePreview = Media::localPath($pose->preview_image_path);
        }

        try {
            // 1) Mankeni pozun yönergesiyle + poz şablonu görseliyle compose et (kimlik korunur).
            $posed = $this->poseComposer->compose(new MannequinPoseRequest(
                referenceImagePath: $refPath,
                label:              (string) ($pose->label ?: $pose->pose_key),
                directive:          (string) $pose->prompt,
                posePreviewPath:    $posePreview,
            ));

            // 2) Ürünü bu poza giydir.
            $out = $this->tryOn->tryOn($posed, $garmentPath);

            // 3) Üretim sonrası kalite iyileştirme (upscale + son dokunuş).
            //    Kapalıysa/başarısızsa $out aynen döner (graceful degrade).
            $enhanced     = $this->enhancer->enhance($out);
            $productImage = $this->persist($enhanced, $product, $pose);

            $result->update([
                'product_image_id' => $productImage->id,
                'status'           => TryonResult::STATUS_DONE,
                'error'            => null,
            ]);

            if ($oldImageId && $oldImageId !== $productImage->id) {
                $this->deleteProductImage(ProductImage::find($oldImageId));
            }

            return $result;
        } finally {
            // $enhanced === $out olabilir (passthrough); delete idempotenttir.
            ImageFile::delete([$posed, $out, $enhanced]);
            $this->resolver->cleanup();
        }
    }

    /**
     * Kapak görselini, yoksa ilk görseli seçer (giysi referansı).
     */
    private function pickGarmentSrc(Product $product): ?string
    {
        $images = $product->images;
        if ($images->isEmpty()) {
            return null;
        }

        $cover = $images->firstWhere('is_cover', true) ?? $images->first();

        // DB'de relative path tutulur; resolver bunu aktif medya diskinden okur
        // (local: doğrudan dosya, R2: temp'e indirme).
        return $cover?->path;
    }

    /**
     * Giydirme çıktısını product_images'a yazar (ürün seviyesi, kapak değil).
     */
    private function persist(string $sourcePath, Product $product, Pose $pose): ProductImage
    {
        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Giydirme çıktısı okunamadı.');
        }

        $rel = sprintf('products/%d/onmodel_%s.png', $product->id, bin2hex(random_bytes(6)));
        Storage::disk(Media::disk())->put($rel, $bytes);

        $sortOrder = (int) $product->images()->max('sort_order') + 1;

        return $product->images()->create([
            'path'       => $rel,
            'alt_text'   => trim($product->name . ' — ' . ($pose->label ?: $pose->pose_key)),
            'sort_order' => $sortOrder,
            'is_cover'   => false,
        ]);
    }

    /**
     * Bir ProductImage'i ve (yerel ise) dosyasını siler.
     */
    private function deleteProductImage(?ProductImage $image): void
    {
        if (! $image) {
            return;
        }

        if ($image->path) {
            Storage::disk(Media::disk())->delete($image->path);
        }

        $image->delete();
    }
}
