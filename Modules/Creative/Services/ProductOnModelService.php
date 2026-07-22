<?php

namespace Modules\Creative\Services;

use App\Support\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Contracts\MannequinPoseComposerContract;
use Modules\Creative\Services\Ai\MannequinPoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\GarmentPrepContract;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductImage;
use RuntimeException;

/**
 * Ürün giydirme orchestrator'ı (asıl çıktı).
 *
 * İki AI adımı: 1) seçilen mankeni, seçilen pozun yönergesiyle compose et
 * (kimlik referansı korunur), 2) ürünü bu poza fashn/tryon ile giydir. Çıktı
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
        private GarmentPrepContract $garmentPrep,
        private ReviewNotifier $notifier,
        private GarmentScanService $garmentScans,
        private GarmentIdentityRuleEngine $identityRules,
    ) {}

    /**
     * Ürün + manken + seçili HAZIR pozlar için tryon_result satırları hazırlar.
     * Yeniden kuyruğa alma, önceki onay durumunu sıfırlar (yeni üretim döngüsü).
     *
     * @param  array<int,int>  $poseIds  Bağımsız poz kütüphanesi id'leri
     * @param  string|null  $garmentImagePath  Ekrandan ayrıca yüklenen giysi görseli
     *                                         (relative path); verilmezse ürünün kendi
     *                                         fotoğrafı kullanılır (bkz. pickGarmentSrc).
     * @param  array<int,array{path:string,label:?string}>  $garmentExtras  Opsiyonel detay
     *                                         görselleri (arkadan/yandan/dikiş vb.); AI
     *                                         giydirmede ek referans olarak kullanılır.
     * @return Collection<int,TryonResult>
     */
    public function queue(Product $product, Mannequin $mannequin, array $poseIds, ?int $creatorId = null, ?string $garmentImagePath = null, array $garmentExtras = []): Collection
    {
        $poses = Pose::query()
            ->where('status', Pose::STATUS_READY)
            ->whereIn('id', $poseIds)
            ->get();

        // meta tamamen ezilmez: sohbetten gelen chat_suggested_instruction /
        // extra_instructions gibi önceki alanlar korunur, sadece garment_extras
        // güncellenir (bkz. ReviewChatController::applyTryon — orada meta merge edilir).
        return $poses->map(function (Pose $pose) use ($product, $mannequin, $garmentImagePath, $garmentExtras, $creatorId) {
            $meta = TryonResult::query()
                ->where('product_id', $product->id)
                ->where('pose_id', $pose->id)
                ->first()?->meta ?? [];

            if ($garmentExtras !== []) {
                $meta['garment_extras'] = $garmentExtras;
            } else {
                unset($meta['garment_extras']);
            }

            return TryonResult::updateOrCreate(
                ['product_id' => $product->id, 'pose_id' => $pose->id],
                [
                    'mannequin_id'       => $mannequin->id,
                    'garment_image_path' => $garmentImagePath,
                    'status'             => TryonResult::STATUS_QUEUED,
                    // Bu satır product_id+pose_id üstünde idempotent (yeniden kuyruğa
                    // alma aynı satırı günceller). Eski bir üretim hâlâ arka planda
                    // sürüyorsa, bitince bu YENİ isteğin verisini ezmemesi için her
                    // queue() çağrısı taze bir token üretir — bkz. generate() sonundaki
                    // token kontrolü ve GenerateOnModelJob.
                    'generation_token'   => (string) Str::uuid(),
                    'error'              => null,
                    'meta'               => $meta !== [] ? $meta : null,
                    'created_by'         => $creatorId,
                    'review_status'      => null,
                    'review_note'        => null,
                    'reviewed_by'        => null,
                    'reviewed_at'        => null,
                ],
            );
        });
    }

    /**
     * Tek bir giydirme sonucunu üretir: mankeni pozla → ürünü giydir → staged_image_path.
     * DB'de product_images'a bağlanmaz; bu ancak {@see publish()} ile (onay sonrası) olur.
     *
     * $expectedToken verilirse, üretim bitiminde satırın generation_token'ı hâlâ bununla
     * eşleşmiyorsa (bu satır daha yeni bir queue() çağrısıyla geçersiz kılınmış) sonuç
     * DB'ye YAZILMAZ — aksi halde uzun süren (birkaç dakikalık) eski bir üretim, kullanıcının
     * arada seçtiği yeni manken/ürün/görsele ait satırın üstüne eski çıktıyı yazabilir.
     */
    public function generate(TryonResult $result, ?string $expectedToken = null): TryonResult
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

        // Ekrandan ayrıca bir giysi görseli yüklendiyse (ürünün fotoğrafı yoksa ya da
        // kullanıcı farklı bir görsel istediyse) o öncelikli; yoksa ürünün kendi fotoğrafı.
        $garmentSrc = $result->garment_image_path ?: $this->pickGarmentSrc($product);
        if (! $garmentSrc) {
            throw new RuntimeException('Ürünün giydirilecek bir görseli yok.');
        }
        $garmentPath = $this->resolver->toLocalPath($garmentSrc);
        if (! $garmentPath) {
            throw new RuntimeException('Ürün görseli yerel yola çözülemedi.');
        }

        // Python (Pillow) ile EXIF düzeltme + düz arka plan kırpma + boyut sınırlama.
        // Devre dışıysa veya başarısız olursa $garmentPath aynen kullanılır.
        $preparedTemps = [];
        $garmentPath   = $this->prepareGarmentImage($garmentPath, $preparedTemps);

        // Giysi parça taraması (yaka/cep/etek vb.) — içerik hash'ine göre dedup
        // edilir, aynı ürün görseli başka bir pozda daha önce tarandıysa yeniden
        // taranmaz (bkz. GarmentScanService::scan). Tespit sürücüsü kapalıysa/model
        // henüz eğitilmediyse boş sonuçla devam eder (graceful degrade).
        $garmentScan = $this->garmentScans->scan($garmentPath);
        $result->update(['garment_scan_id' => $garmentScan->id]);

        // Opsiyonel detay görselleri (arkadan/yandan/yaka-dikiş/kumaş vb.) — TryonController
        // tarafından meta.garment_extras'a yazılmış relative path + label çiftleri.
        $garmentExtras = [];
        foreach ((array) ($result->meta['garment_extras'] ?? []) as $extra) {
            $extraSrc = $extra['path'] ?? null;
            if (! $extraSrc) {
                continue;
            }
            $extraPath = $this->resolver->toLocalPath($extraSrc);
            if (! $extraPath) {
                continue;
            }
            $extraPath = $this->prepareGarmentImage($extraPath, $preparedTemps);
            $garmentExtras[] = ['path' => $extraPath, 'label' => $extra['label'] ?? null];
        }

        // Otomatik tespit edilen, manuel yüklenen detaylarla çakışmayan yüksek
        // güvenli parçalar da ek referans olarak eklenir — Gemini'ye "yaka kısmı
        // bu, etek kısmı bu" diye anlatan mekanizma budur (bkz. describeExtras).
        // NOT: crop yolları artık KALICI (GarmentScanService::scan sırasında bir
        // kez üretilir); $preparedTemps'e eklenmez — aksi halde paylaşılan crop
        // dosyası bu üretim bitince silinir, bir sonraki poz için kullanılamaz.
        $autoCrops = $this->garmentScans->cropsForTryOn(
            $garmentScan,
            $garmentExtras,
            (int) config('creative.garment_detection.max_auto_crops', 4),
            (float) config('creative.garment_detection.min_confidence', 0.35),
        );
        $garmentExtras = [...$garmentExtras, ...$autoCrops];

        // Ham analiz JSON'u ASLA doğrudan prompt'a yazılmaz — confidence eşiği
        // altındaki alanları eleyen, nihai önceliği hesaplayan Rule Engine'den
        // geçirilir (bkz. ROADMAP.md Faz G.5, kullanıcı geri bildirimi madde 8).
        $directives           = $this->identityRules->directivesFor($garmentExtras);
        $protectListSentence  = $this->identityRules->protectListSentence($directives);

        $posed    = null;
        $out      = null;
        $enhanced = null;

        // Manken poz compose + try-on + iyileştirme adımlarının toplam süresi —
        // raporlama/detay sayfasında hangi modelin ne kadar sürdüğünü göstermek için.
        $startedAt = microtime(true);

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

            // 2) Ürünü bu poza giydir (destekleyen sürücülerde ek açı/detay görselleri VE
            //    ret sonrası düzeltme talimatıyla — bkz. $extra yukarıda). Ret çoğunlukla
            //    giydirme adımıyla ilgili olduğu için (renk/oturma/detay) talimat burada da
            //    verilmezse "yeniden üret" aynı hatalı sonucu üretir.
            $out = $this->tryOn->tryOn($posed, $garmentPath, $directives, $extra !== '' ? $extra : null, $protectListSentence);

            // 3) Üretim sonrası kalite iyileştirme (upscale + son dokunuş).
            //    Kapalıysa/başarısızsa $out aynen döner (graceful degrade).
            $enhanced = $this->enhancer->enhance($out);

            // AI çağrıları dakikalarca sürebilir; bu süre içinde kullanıcı aynı satırı
            // (product_id+pose_id) yeni bir manken/ürün/görselle yeniden kuyruğa almış
            // olabilir. Böyle bir durumda bu üretim ARTIK GEÇERSİZ — DB'yi ezmeden çık.
            if ($expectedToken !== null && $result->fresh()?->generation_token !== $expectedToken) {
                return $result;
            }

            $staged = $this->persistStaged($enhanced, $product, $result);

            $result->update([
                'staged_image_path' => $staged,
                'status'            => TryonResult::STATUS_DONE,
                // config('creative.ai.tryon_driver') İSTENEN sürücüyü söyler; anahtar
                // eksikse binding sessizce mock'a düşebileceğinden (CreativeServiceProvider),
                // burada GERÇEKTEN çalışan sürücü, çözülen instance'ın namespace'inden okunur.
                'tryon_driver'      => $this->resolveTryOnDriverName(),
                'tryon_model'       => $this->tryOn->modelIdentifier(),
                'generation_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
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
            ImageFile::delete([$posed, $out, $enhanced, ...$preparedTemps]);
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
     * Enjekte edilen GarmentTryOnContract implementasyonunun namespace'inden gerçek
     * sürücü adını çıkarır (fal|gemini|mock) — raporlarda config'teki İSTENEN sürücü
     * değil, o üretimde FİİLEN çalışan sürücü görünsün diye.
     */
    private function resolveTryOnDriverName(): string
    {
        $class = get_class($this->tryOn);

        return match (true) {
            str_contains($class, '\\Drivers\\Fal\\')    => 'fal',
            str_contains($class, '\\Drivers\\Gemini\\') => 'gemini',
            default                                     => 'mock',
        };
    }

    /**
     * Ürünün kendi galerisinden giysi referansı seçer (kapak, yoksa ilk görsel).
     * Sadece result->garment_image_path boşsa (ekrandan ayrıca yükleme yapılmadıysa)
     * kullanılır; bkz. generate().
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
     * Bir giysi görselini (ana ya da detay) garmentPrep ile hazırlar. Hazırlık yeni
     * bir geçici dosya üretirse (orijinalden farklı yol) $preparedTemps'e eklenir —
     * generate()'in finally bloğu bunu temizler. Hazırlık kapalı/başarısızsa
     * $path aynen döner ve hiçbir şey silinmez (orijinal disk dosyası korunur).
     *
     * @param  array<int,string>  $preparedTemps
     */
    private function prepareGarmentImage(string $path, array &$preparedTemps): string
    {
        $prepared = $this->garmentPrep->prepare($path);
        if ($prepared !== $path) {
            $preparedTemps[] = $prepared;
        }

        return $prepared;
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
