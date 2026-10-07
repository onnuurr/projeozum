<?php

namespace Modules\Creative\Services\Mockup;

use Modules\Creative\Jobs\GenerateMockupJob;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\TryonResult;
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
        private MockupSettings $settings,
    ) {}

    /**
     * Ürün için mokap akışını başlatır (kombin satırı + kuyruk işi).
     *
     * @param  string|null  $garmentPath  Ürünün yalın fotoğrafı yoksa kullanılacak giysi görseli (medya diski relative)
     * @param  bool|null  $batch  Görsel çağrıları Batch API ile (%50 ucuz, gecikmeli); null → config creative.mockup.batch
     */
    public function start(Product $product, Mannequin $mannequin, ?string $region = null, ?string $length = null, ?string $garmentPath = null, ?int $creatorId = null, bool $dispatch = true, ?bool $batch = null, array $meta = []): MockupOutfit
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
            'meta'          => array_filter([...$meta, 'garment_path' => $garmentPath, 'batch' => $batch ?? (bool) config('creative.mockup.batch', true)]),
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
     * Arayüzden / otomatik tetikten başlatma. Manken verilirse yalnız o manken için tek kombin; verilmezse
     * ürünün EKSİK beden grupları (MockupSettings::sizeGroups — sitede beden seçilince o yaştaki mankenin
     * görselleri) için birer kombin. Her kombin günlük limitten bir hak düşer; devam eden / hazır grup atlanır.
     *
     * @return list<MockupOutfit> başlatılan kombinler
     */
    public function startFor(Product $product, ?Mannequin $mannequin = null, ?int $creatorId = null, ?int $max = null): array
    {
        $groups = $mannequin
            ? [['mannequin' => $mannequin, 'sizes' => [], 'ages' => null, 'note' => null]]
            : $this->missingGroups($product);
        if ($groups === []) {
            // Gruplar tamam ama sonradan fotoğrafı eklenen renk olabilir: yalnız o rengin giydirmesi (baz yeniden üretilmez).
            if (! $mannequin && $this->queueMissingColors($product, $creatorId) > 0) {
                return [];
            }
            throw new RuntimeException(__($this->settings->sizeGroups($product) === []
                ? 'creative::messages.mockup.no_default_mannequin' : 'creative::messages.mockup.already_complete'));
        }

        $started = [];
        foreach ($groups as $g) {
            if (($max !== null && count($started) >= $max) || $this->settings->remainingToday() <= 0) {
                break;
            }
            if ($this->hasActiveOutfit($product, $g['mannequin'])) {
                continue;
            }
            $started[] = $this->start($product, $g['mannequin'], creatorId: $creatorId, meta: array_filter([
                'mannequin_note' => $g['note'],
                'sizes'          => $g['sizes'] ?: null,
                'ages'           => $g['ages'],
            ]));
        }

        if ($started === []) {
            throw new RuntimeException($this->settings->remainingToday() <= 0
                ? __('creative::messages.mockup.daily_limit', ['limit' => $this->settings->dailyLimit()])
                : __('creative::messages.mockup.already_running'));
        }

        return $started;
    }

    /**
     * Ürünün henüz kombini olmayan (ya da yalnız başarısız kombini olan) beden grupları.
     *
     * @return list<array{mannequin:Mannequin, sizes:list<string>, ages:?array{0:int,1:int}, note:?string}>
     */
    public function missingGroups(Product $product): array
    {
        $have = MockupOutfit::where('product_id', $product->id)->where('status', '!=', MockupOutfit::STATUS_FAILED)->pluck('mannequin_id')->all();

        return array_values(array_filter($this->settings->sizeGroups($product), fn ($g) => ! in_array($g['mannequin']->id, $have, true)));
    }

    /**
     * Hazır kombinlerde giydirmesi olmayan renkler (fotoğrafı sonradan eklenen renk): kombin => renkler.
     *
     * @return array<int,list<string>>
     */
    public function missingColors(Product $product): array
    {
        $outfits = MockupOutfit::where('product_id', $product->id)->where('status', MockupOutfit::STATUS_READY)->get();
        if ($outfits->isEmpty()) {
            return [];
        }
        $wanted = array_keys($this->onModel->colorGarments($product)['garments']);
        $out = [];
        foreach ($outfits as $o) {
            $have = TryonResult::where(['product_id' => $product->id, 'mannequin_id' => $o->mannequin_id,
                'pipeline' => TryonResult::PIPELINE_MOCKUP, 'shot' => TryonResult::SHOT_FULL])->distinct()->pluck('color')->all();
            if ($missing = array_values(array_diff($wanted, $have))) {
                $out[$o->id] = $missing;
            }
        }

        return $out;
    }

    /** Eksik renklerin giydirmelerini mevcut bazlarla kuyruğa alır. @return int kuyruğa alınan satır */
    public function queueMissingColors(Product $product, ?int $creatorId = null): int
    {
        $count = 0;
        foreach ($this->missingColors($product) as $outfitId => $colors) {
            $outfit = MockupOutfit::find($outfitId);
            $bases = MockupBase::where('outfit_id', $outfitId)->where('status', MockupBase::STATUS_READY)->orderBy('id')->get();
            foreach ($this->tryOn->queue($outfit, $bases, $creatorId ?? $outfit->created_by, $colors) as $result) {
                GenerateOnModelJob::dispatch($result->id, $result->generation_token);
                $count++;
            }
        }

        return $count;
    }

    private function hasActiveOutfit(Product $product, Mannequin $mannequin): bool
    {
        return MockupOutfit::where(['product_id' => $product->id, 'mannequin_id' => $mannequin->id])
            ->where('status', '!=', MockupOutfit::STATUS_FAILED)->exists();
    }

    /**
     * Toplu başlatma / kapsam doldurma: eksik beden grubu olan ürünlerin gruplarını, en fazla $limit
     * kombin (ve günlük kalan hak) kadar başlatır.
     *
     * @return array{started:int, skipped:int}
     */
    public function startBulk(int $limit, ?int $creatorId = null): array
    {
        $started = 0;
        $skipped = 0;
        foreach ($this->candidates() as $product) {
            if ($started >= $limit || $this->settings->remainingToday() <= 0) {
                break;
            }
            try {
                $started += count($this->startFor($product, null, $creatorId, $limit - $started));
            } catch (RuntimeException) {
                $skipped++;
            }
        }

        return ['started' => $started, 'skipped' => $skipped];
    }

    /**
     * Eksik beden grubu olan ürünler: fotoğraflı, kategorisi (ya da üstü) eşleştirilmiş, yalın
     * fotoğraflı. Eski ürünler önce.
     *
     * @return \Illuminate\Support\Collection<int,Product>
     */
    public function candidates(int $max = 200): \Illuminate\Support\Collection
    {
        return Product::query()->whereHas('images')
            ->with(['images', 'category.parent'])->orderBy('id')->limit($max)->get()
            ->filter(fn (Product $p) => $this->settings->regionFor($p->category) !== null && $this->onModel->pickPlainProductPhoto($p)
                && ($this->missingGroups($p) !== [] || $this->missingColors($p) !== []))
            ->values();
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

    /** Ürünün stilistten geçmiş başka kombininin parçalarını, cümlesini ve pozlarını bu kombine kopyalar. */
    private function copyLookFromSibling(MockupOutfit $outfit): ?MockupOutfit
    {
        $sibling = MockupOutfit::query()->where('product_id', $outfit->product_id)->whereKeyNot($outfit->id)
            ->where('region', $outfit->region)->whereNotNull('outfit_sentence')->where('outfit_sentence', '!=', '')
            ->where('status', '!=', MockupOutfit::STATUS_FAILED)->oldest('id')->first();
        if (! $sibling) {
            return null;
        }

        $outfit->update([
            'length'                 => $outfit->length ?: $sibling->length,
            'pieces'                 => $sibling->pieces,
            'outfit_sentence'        => $sibling->outfit_sentence,
            'poses'                  => $sibling->poses,
            'reasoning_tr'           => $sibling->reasoning_tr,
            'stylist_prompt_version' => $sibling->stylist_prompt_version,
            'meta'                   => [...($outfit->meta ?? []),
                ...array_intersect_key((array) $sibling->meta, array_flip(['product_summary', 'stylist_confidence', 'stylist_warnings', 'product_features'])),
                'look_from_outfit' => $sibling->id],
        ]);

        return $outfit->fresh();
    }

    /**
     * Stilistin göreceği ek fotoğraflar: ana fotoğrafla aynı renk setindeki diğer yalın fotoğraflar
     * (ayrı çekilmiş takım parçaları stilistin ürünü — ve boyunu — doğru anlaması için).
     *
     * @return list<string> yerel yollar
     */
    private function extraPhotos(Product $product, string $src): array
    {
        $plan = $this->onModel->colorGarments($product);
        $color = array_search($src, $plan['garments'], true);
        $extras = $color !== false ? ($plan['extras'][$color] ?? []) : (array) (reset($plan['extras']) ?: []);

        return array_values(array_filter(array_map(fn (string $p) => $this->resolver->toLocalPath($p), array_diff($extras, [$src]))));
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
                // Aynı ürünün başka beden grubu stilistten geçtiyse kombin + pozlar aynen kullanılır
                // (sitede beden değişince yalnız manken değişir); yoksa stilist çalışır.
                $outfit = $this->copyLookFromSibling($outfit) ?? $this->stylist->style($outfit, $photo, $this->extraPhotos($product, $src));
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
