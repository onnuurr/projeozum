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
 * (kimlik referansı korunur), 2) ürünü bu poza idm-vton ile giydir. Çıktı
 * ÜRETİM bitince product_images'a DEĞİL, staged_image_path'e yazılır — insan
 * onayı olmadan mağazada görünmez. Onay anında {@see publish()} çağrılır ve
 * ancak o zaman Product modülünün product_images tablosuna satır eklenir.
 * İzlenebilirlik/idempotensi: creative_tryon_results (product_id+pose_id benzersiz).
 */
class ProductOnModelService
{
    public function __construct(
        private MannequinPoseComposerContract $poseComposer,
        private GarmentTryOnContract $tryOn,
        private CanvasAssetResolver $resolver,
        private ImageEnhancerContract $enhancer,
        private ReviewNotifier $notifier,
    ) {}

    /**
     * Ürün + manken + seçili HAZIR pozlar için tryon_result satırları hazırlar.
     * Yeniden kuyruğa alma, önceki onay durumunu sıfırlar (yeni üretim döngüsü).
     *
     * @param  array<int,int>  $poseIds  Bağımsız poz kütüphanesi id'leri
     * @return Collection<int,TryonResult>
     */
    public function queue(Product $product, Mannequin $mannequin, array $poseIds, ?int $creatorId = null): Collection
    {
        $poses = Pose::query()
            ->where('status', Pose::STATUS_READY)
            ->whereIn('id', $poseIds)
            ->get();

        return $poses->map(fn (Pose $pose) => TryonResult::updateOrCreate(
            ['product_id' => $product->id, 'pose_id' => $pose->id],
            [
                'mannequin_id'  => $mannequin->id,
                'status'        => TryonResult::STATUS_QUEUED,
                'error'         => null,
                'created_by'    => $creatorId,
                'review_status' => null,
                'review_note'   => null,
                'reviewed_by'   => null,
                'reviewed_at'   => null,
            ],
        ));
    }

    /**
     * Tek bir giydirme sonucunu üretir: mankeni pozla → ürünü giydir → staged_image_path.
     * DB'de product_images'a bağlanmaz; bu ancak {@see publish()} ile (onay sonrası) olur.
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

        $posed    = null;
        $out      = null;
        $enhanced = null;

        // Pozun önizleme görseli birebir duruş referansı olarak kullanılır (varsa).
        $posePreview = null;
        if ($pose->preview_image_path) {
            $posePreview = Media::localPath($pose->preview_image_path);
        }

        // Ret sonrası chatbot ile üretilen düzeltme talimatı varsa (bkz. ReviewChatService),
        // poz yönergesine EK olarak eklenir — yapısal alanları (kimlik, ölçü) ezmez.
        $directive = (string) $pose->prompt;
        $extra     = trim((string) ($result->meta['extra_instructions'] ?? ''));
        if ($extra !== '') {
            $directive = trim($directive . '. ' . $extra);
        }

        try {
            // 1) Mankeni pozun yönergesiyle + poz şablonu görseliyle compose et (kimlik korunur).
            $posed = $this->poseComposer->compose(new MannequinPoseRequest(
                referenceImagePath: $refPath,
                label:              (string) ($pose->label ?: $pose->pose_key),
                directive:          $directive,
                posePreviewPath:    $posePreview,
            ));

            // 2) Ürünü bu poza giydir.
            $out = $this->tryOn->tryOn($posed, $garmentPath);

            // 3) Üretim sonrası kalite iyileştirme (upscale + son dokunuş).
            //    Kapalıysa/başarısızsa $out aynen döner (graceful degrade).
            $enhanced = $this->enhancer->enhance($out);
            $staged   = $this->persistStaged($enhanced, $product, $result);

            $result->update([
                'staged_image_path' => $staged,
                'status'            => TryonResult::STATUS_DONE,
                'error'             => null,
                'review_status'     => TryonResult::REVIEW_PENDING,
                'review_note'       => null,
                'reviewed_by'       => null,
                'reviewed_at'       => null,
            ]);

            $this->notifier->notifyPending($result, $result->created_by);

            return $result;
        } finally {
            // $enhanced === $out olabilir (passthrough); delete idempotenttir.
            ImageFile::delete([$posed, $out, $enhanced]);
            $this->resolver->cleanup();
        }
    }

    /**
     * Onaylanmış bir giydirme sonucunu Product modülünün product_images tablosuna
     * yazar — bu, mağazada gerçekten görünür hale geldiği tek an. Sadece review
     * controller'ı (approve) tarafından çağrılır.
     */
    public function publish(TryonResult $result): TryonResult
    {
        if (! $result->staged_image_path) {
            throw new RuntimeException('Onaylanacak bir görsel yok; önce üretim tamamlanmalı.');
        }

        $product = $result->product;
        $pose    = $result->pose;
        if (! $product) {
            throw new RuntimeException('Giydirme için ürün bulunamadı.');
        }

        $oldImageId = $result->product_image_id;
        $sortOrder  = (int) $product->images()->max('sort_order') + 1;

        $image = $product->images()->create([
            'path'       => $result->staged_image_path,
            'alt_text'   => trim($product->name . ' — ' . ($pose?->label ?: $pose?->pose_key)),
            'sort_order' => $sortOrder,
            'is_cover'   => false,
        ]);

        $result->update(['product_image_id' => $image->id]);

        // Aynı sonuç daha önce yayınlanmışsa (örn. çift onay), eski satırı temizle.
        // Dosyayı SİLME: staged_image_path ile aynı fiziksel dosyayı paylaşıyor olabilir.
        if ($oldImageId && $oldImageId !== $image->id) {
            ProductImage::find($oldImageId)?->delete();
        }

        return $result->fresh();
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
     * Giydirme çıktısını onay bekleyen (staged) sabit bir yola yazar — product_images'a
     * DEĞİL. Yol sonuç id'sine göre deterministiktir; yeniden üretim aynı dosyayı ezer,
     * disk şişmesi/yetim dosya birikmez.
     */
    private function persistStaged(string $sourcePath, Product $product, TryonResult $result): string
    {
        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Giydirme çıktısı okunamadı.');
        }

        $rel = sprintf('products/%d/onmodel_staged_%d.png', $product->id, $result->id);
        Storage::disk(Media::disk())->put($rel, $bytes);

        return $rel;
    }

    /**
     * Bir giydirme sonucunu tamamen kaldırır: bağlı ProductImage'i (varsa, dosyasıyla)
     * ve staged (henüz onaylanmamış) dosyayı siler, ardından satırı siler.
     */
    public function destroy(TryonResult $result): void
    {
        $result->loadMissing('productImage');
        $this->deleteProductImage($result->productImage);

        if ($result->staged_image_path) {
            Storage::disk(Media::disk())->delete($result->staged_image_path);
        }

        $result->delete();
    }

    /**
     * Bir ProductImage'i ve (yerel ise) dosyasını siler. staged_image_path ile aynı
     * dosyayı paylaşabileceğinden, sadece destroy() içinde kullanılır — publish()
     * içindeki dedup'ta dosya silinmez, sadece eski DB satırı kaldırılır.
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
