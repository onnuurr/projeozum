<?php

namespace Modules\Creative\Services\Mockup;

use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupOutfit;
use Modules\Product\Models\Product;
use Modules\Creative\Services\CanvasAssetResolver;
use Modules\Creative\Services\ProductOnModelService;
use RuntimeException;

/**
 * Mokap giydirme orkestratörü: ürün → bölge/boy → kombin (stilist) → baz görseller →
 * giydirme işleri (her poz için GenerateOnModelJob, pipeline=mockup) → onay kuyruğu.
 * Son onay her zaman insandadır; bu servis hiçbir şeyi yayınlamaz.
 */
class MockupPipelineService
{
    public function __construct(
        private MockupProductTypeResolver $types,
        private MockupStylistService $stylist,
        private MockupBaseService $bases,
        private MockupTryOnService $tryOn,
        private ProductOnModelService $onModel,
        private CanvasAssetResolver $resolver,
    ) {}

    /**
     * Ürün için mokap akışını başlatır (kombin satırı + kuyruk işi).
     *
     * @param  string|null  $garmentPath  Ürünün yalın fotoğrafı yoksa kullanılacak giysi görseli (medya diski relative)
     * @param  bool|null  $batch  Görsel çağrıları Batch API ile (%50 ucuz, gecikmeli); null → config creative.mockup.batch
     */
    public function start(Product $product, Mannequin $mannequin, ?string $region = null, ?string $length = null, ?string $garmentPath = null, ?int $creatorId = null, bool $dispatch = true, ?bool $batch = null): MockupOutfit
    {
        if ($mannequin->status !== Mannequin::STATUS_READY || $mannequin->review_status !== Mannequin::REVIEW_APPROVED) {
            throw new RuntimeException(__('creative::messages.mockup.mannequin_not_ready'));
        }
        $garmentPath = $this->normalizeGarmentPath($garmentPath);
        if (! $garmentPath && ! $this->onModel->pickPlainProductPhoto($product->loadMissing('images'))) {
            throw new RuntimeException(__('creative::messages.mockup.no_product_photo'));
        }

        $type = $this->types->resolve($product, $region, $length);

        $outfit = MockupOutfit::create([
            'product_id'    => $product->id,
            'mannequin_id'  => $mannequin->id,
            'region'        => $type['region'],
            'length'        => $type['length'] ?? '',
            'length_source' => $type['length_source'],
            'status'        => MockupOutfit::STATUS_QUEUED,
            'meta'          => array_filter(['garment_path' => $garmentPath, 'batch' => $batch ?? (bool) config('creative.mockup.batch', true)]),
            'created_by'    => $creatorId,
        ]);

        // Çağıran işi kendi sürecinde çalıştıracaksa (komut --sync) kuyruğa GÖNDERİLMEZ — aksi halde
        // aynı kombin iki kez paralel üretilir (2026-10-06: çift maliyet + karışan baz dosyaları).
        if ($dispatch) {
            GenerateMockupJob::dispatch($outfit->id);
        }

        return $outfit;
    }

    /**
     * Bir ürünün mokap maliyeti için ÖN TAHMİN (yeniden denemeler hariç): 1 stilist analizi +
     * (ana kare + ek pozlar) baz görsel + aynı sayıda giydirme. Fiyat: config creative.ai.pricing.
     *
     * @return array{images:int, analyses:int, usd:float}
     */
    public function estimate(?bool $batch = null): array
    {
        $poses = 1 + (int) config('creative.mockup.extra_poses', 2);
        $model = (string) config('creative.mockup.image_model');
        $price = (array) (config('creative.ai.pricing.gemini', [])[$model] ?? []);
        $at = fn (string $key) => (float) ($price['image'][(string) config("creative.mockup.{$key}", '1K')] ?? 0.101);
        // analizler: stilist + (Faz 2, açıksa) türetilen poz başına tutarlılık + poz başına sadakat (~0,004 $, anlık)
        $analyses = 1 + (config('creative.mockup.qa.enabled', true) ? ($poses - 1) + $poses : 0);
        // görsel çağrıları: çıktı + girdi (~3 referans × ~1120 token); batch'te yarı fiyat
        $images = $poses * ($at('base_image_size') + $at('tryon_image_size')) + $poses * 2 * 3 * 1120 / 1e6 * (float) ($price['input_per_mtok'] ?? 0.5);
        $k = ($batch ?? (bool) config('creative.mockup.batch', true)) ? (float) config('creative.ai.pricing.batch_multiplier', 0.5) : 1.0;

        return ['images' => $poses * 2, 'analyses' => $analyses, 'usd' => round($k * $images + $analyses * 0.004, 3),
            'retry_usd' => round($k * $at('tryon_image_size'), 3)];
    }

    /**
     * Verilen giysi görseli yolunu doğrular ve kalıcı biçime getirir: mutlak yol, başındaki "/"
     * unutulmuş mutlak yol, proje kökünden göreli yol (ör. public/images/...) → mutlak yol;
     * medya diskindeki relative yol → olduğu gibi. Hiçbiri değilse kombin oluşturulmadan hata verir.
     */
    private function normalizeGarmentPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        foreach ([$path, '/' . ltrim($path, '/'), base_path(ltrim($path, '/'))] as $candidate) {
            if (str_starts_with($candidate, '/') && is_file($candidate)) {
                return $candidate;
            }
        }
        if (\Illuminate\Support\Facades\Storage::disk(\App\Support\Media::disk())->exists(ltrim($path, '/'))) {
            return ltrim($path, '/');
        }

        throw new RuntimeException(__('creative::messages.mockup.garment_not_found', ['path' => $path]));
    }

    /**
     * Kuyruk işinin gövdesi: stilist → bazlar → giydirme işleri. Batch modunda bazlar beklenirken
     * kombin "generating" kalır; sonuç gelince iş yeniden çalışır ve kaldığı yerden devam eder
     * (stilist yeniden çağrılmaz, hazır bazlar yeniden üretilmez). Aynı kombinin iki kopyasının
     * aynı anda çalışmasını GenerateMockupJob'daki kilit engeller.
     */
    public function run(MockupOutfit $outfit): MockupOutfit
    {
        // Yalnız "queued" (ilk çalışma) ya da "generating" (batch sonrası devam) kombin işlenir;
        // bitmiş kombin için gelen fazladan kopya hiçbir AI çağrısı yapmadan çıkar.
        MockupOutfit::whereKey($outfit->id)->where('status', MockupOutfit::STATUS_QUEUED)
            ->update(['status' => MockupOutfit::STATUS_GENERATING, 'error' => null]);
        $outfit->refresh();
        if ($outfit->status !== MockupOutfit::STATUS_GENERATING) {
            return $outfit;
        }

        try {
            $product = $outfit->product()->with('images')->firstOrFail();
            $src = ($outfit->meta['garment_path'] ?? null) ?: $this->onModel->pickPlainProductPhoto($product);
            $photo = $src ? $this->resolver->toLocalPath($src) : null;
            if (! $photo) {
                throw new RuntimeException(__('creative::messages.mockup.no_product_photo'));
            }

            if (trim((string) $outfit->outfit_sentence) === '') {
                $outfit = $this->stylist->style($outfit, $photo);
            }
            $ready = $this->bases->generate($outfit);
            if ($ready === null) {
                return $outfit->fresh();      // batch sonucu bekleniyor
            }
            if ($ready->isEmpty()) {
                throw new RuntimeException(__('creative::messages.mockup.no_ready_base'));
            }

            foreach ($this->tryOn->queue($outfit, $ready, $outfit->created_by) as $result) {
                GenerateOnModelJob::dispatch($result->id, $result->generation_token);
            }

            $outfit->update(['status' => MockupOutfit::STATUS_READY]);
        } catch (\Throwable $e) {
            $outfit->update(['status' => MockupOutfit::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            $this->resolver->cleanup();
        }

        return $outfit->fresh();
    }
}
