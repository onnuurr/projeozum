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
     */
    public function start(Product $product, Mannequin $mannequin, ?string $region = null, ?string $length = null, ?string $garmentPath = null, ?int $creatorId = null, bool $dispatch = true): MockupOutfit
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
            'meta'          => array_filter(['garment_path' => $garmentPath]),
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
    public function estimate(): array
    {
        $poses = 1 + (int) config('creative.mockup.extra_poses', 2);
        $model = (string) config('creative.mockup.image_model');
        $price = (array) (config('creative.ai.pricing.gemini', [])[$model] ?? []);
        $at = fn (string $key) => (float) ($price['image'][(string) config("creative.mockup.{$key}", '1K')] ?? 0.101);
        // stilist analizi + görsel girdileri (~3 referans × ~1120 token) — küçük ama sıfır değil
        $input = 0.002 + $poses * 2 * 3 * 1120 / 1e6 * (float) ($price['input_per_mtok'] ?? 0.5);

        return ['images' => $poses * 2, 'analyses' => 1, 'usd' => round($poses * ($at('base_image_size') + $at('tryon_image_size')) + $input, 3)];
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

    /** Kuyruk işinin gövdesi: stilist → bazlar → giydirme işleri. */
    public function run(MockupOutfit $outfit): MockupOutfit
    {
        // Atomik sahiplenme: yalnız "queued" durumundaki kombin işlenir. Aynı kombin için ikinci bir
        // kopya (çift dispatch, worker yeniden denemesi) hiçbir AI çağrısı yapmadan çıkar.
        $claimed = MockupOutfit::whereKey($outfit->id)->where('status', MockupOutfit::STATUS_QUEUED)
            ->update(['status' => MockupOutfit::STATUS_GENERATING, 'error' => null]);
        if ($claimed === 0) {
            return $outfit->fresh();
        }
        $outfit->refresh();

        try {
            $product = $outfit->product()->with('images')->firstOrFail();
            $src = ($outfit->meta['garment_path'] ?? null) ?: $this->onModel->pickPlainProductPhoto($product);
            $photo = $src ? $this->resolver->toLocalPath($src) : null;
            if (! $photo) {
                throw new RuntimeException(__('creative::messages.mockup.no_product_photo'));
            }

            $outfit = $this->stylist->style($outfit, $photo);
            $ready = $this->bases->generate($outfit);
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
