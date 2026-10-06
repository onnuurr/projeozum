<?php

namespace Modules\Creative\Services\Mockup;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Creative\Jobs\GenerateOnModelJob;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\AiBatchService;
use Modules\Creative\Services\CanvasAssetResolver;
use Modules\Creative\Services\ProductOnModelService;
use Modules\Creative\Services\ReviewNotifier;
use RuntimeException;

/**
 * Mokap giydirme: baz görselin bölgesini kırpar, Gemini ile ürünü giydirir, sonucu baz görselle
 * birleştirir (maske dışı birebir bazdan) ve staged olarak yazar. Sonuç satırı (pipeline=mockup)
 * mevcut onay → yayın akışından geçer (ProductOnModelService::publish).
 */
class MockupTryOnService
{
    public function __construct(
        private MockupGeneratorContract $generator,
        private MockupVisionContract $vision,
        private ProductOnModelService $onModel,
        private CanvasAssetResolver $resolver,
        private ReviewNotifier $notifier,
        private AiBatchService $batch,
    ) {}

    /**
     * Hazır bazlar için sonuç satırlarını hazırlar (yeniden kuyruğa alma aynı satırı günceller ve
     * onayı sıfırlar). Mevcut legacy satırlarına dokunmaz (tekillik pipeline'ı da içerir).
     *
     * @param  Collection<int,MockupBase>  $bases
     * @return Collection<int,TryonResult>
     */
    public function queue(MockupOutfit $outfit, Collection $bases, ?int $creatorId = null): Collection
    {
        return $bases->map(function (MockupBase $base) use ($outfit, $creatorId) {
            $existing = TryonResult::query()->where([
                'product_id' => $outfit->product_id, 'pose_id' => $base->pose_id,
                'shot' => TryonResult::SHOT_FULL, 'pipeline' => TryonResult::PIPELINE_MOCKUP,
            ])->first();
            $meta = $existing?->meta ?? [];
            unset($meta['extra_instructions'], $meta['mockup_run']);   // yeni baz → eski düzeltme talimatı geçersiz
            $meta['batch'] = (bool) ($outfit->meta['batch'] ?? false);

            return TryonResult::updateOrCreate(
                ['product_id' => $outfit->product_id, 'pose_id' => $base->pose_id, 'shot' => TryonResult::SHOT_FULL, 'pipeline' => TryonResult::PIPELINE_MOCKUP],
                [
                    'mannequin_id'       => $outfit->mannequin_id,
                    'mockup_base_id'     => $base->id,
                    'garment_image_path' => $outfit->meta['garment_path'] ?? null,
                    'status'             => TryonResult::STATUS_QUEUED,
                    'generation_token'   => (string) Str::uuid(),
                    'error'              => null,
                    'qa'                 => null,
                    'meta'               => $meta,
                    'created_by'         => $creatorId,
                    'review_status'      => null,
                    'review_note'        => null,
                    'reviewed_by'        => null,
                    'reviewed_at'        => null,
                ],
            );
        });
    }

    public function generate(TryonResult $result, ?string $expectedToken = null): TryonResult
    {
        $base = $result->mockupBase()->with('outfit')->first();
        $outfit = $base?->outfit;
        $product = $result->product()->with('images')->first();
        if (! $base || ! $outfit || $base->status !== MockupBase::STATUS_READY || ! $base->image_path) {
            throw new RuntimeException(__('creative::messages.mockup.base_missing'));
        }
        if (! $product) {
            throw new RuntimeException(__('creative::messages.tryon.product_not_found'));
        }

        $disk = Storage::disk((string) config('creative.mockup.work_disk', 'local'));
        $basePath = $disk->path($base->image_path);
        $prefix = $disk->path($base->masks['prefix']);
        $box = array_map('intval', $base->masks['crop']);

        $temps = [];
        $startedAt = microtime(true);

        try {
            $garmentSrc = $result->garment_image_path ?: $this->onModel->pickPlainProductPhoto($product);
            $garment = $garmentSrc ? $this->resolver->toLocalPath($garmentSrc) : null;
            if (! $garment) {
                throw new RuntimeException(__('creative::messages.mockup.no_product_photo'));
            }
            $images = [$this->onModel->prepareGarmentImage($garment, $temps)];
            foreach ((array) ($result->meta['garment_extras'] ?? []) as $extra) {
                $p = ! empty($extra['path']) ? $this->resolver->toLocalPath($extra['path']) : null;
                if ($p) {
                    $images[] = $this->onModel->prepareGarmentImage($p, $temps);
                }
            }

            $temps[] = $crop = $this->tempPath();
            $this->vision->crop($basePath, $box, $crop);

            // Faz 2: giydir → birleştir → hiza + sadakat kontrolü. Kalırsa bulgular düzeltme talimatı
            // olarak verilip EN FAZLA qa.max_retries kez yeniden denenir; yine kalırsa sonuç uyarıyla
            // onaya düşer (insan onayı hiçbir durumda atlanmaz).
            $enabled = (bool) config('creative.mockup.qa.enabled', true);
            $max = $enabled ? max(0, (int) config('creative.mockup.qa.max_retries', 1)) : 0;
            $restore = (bool) ($base->masks['long'] ?? false) && $outfit->region !== 'top';
            $view = (string) ($base->pose?->view ?: 'front');

            // Batch modunda giydirme sonucu gelene kadar iş biter; deneme no + düzeltme talimatı
            // meta.mockup_run'da kalır, iş yeniden çalışınca aynı denemeden (aynı batch anahtarı) devam eder.
            $run = (array) ($result->meta['mockup_run'] ?? []);
            $resumed = ($run['token'] ?? null) === $result->generation_token;
            $attempt = $resumed ? (int) ($run['attempt'] ?? 0) : 0;
            $correction = $resumed ? ($run['correction'] ?? null) : (trim((string) ($result->meta['extra_instructions'] ?? '')) ?: null);
            $batch = (bool) ($result->meta['batch'] ?? false);

            for (; ; $attempt++) {
                $out = $this->batch->defer(
                    $batch ? "mockup_tryon:{$result->id}:{$result->generation_token}:{$attempt}" : null,
                    $result,
                    ['job' => GenerateOnModelJob::class, 'args' => [$result->id, $result->generation_token]],
                    fn () => $this->generator->tryOn($crop, $images, $outfit->region, $outfit->length, $correction),
                );
                if ($out === null) {
                    $result->update(['meta' => [...($result->meta ?? []),
                        'mockup_run' => ['token' => $result->generation_token, 'attempt' => $attempt, 'correction' => $correction]]]);

                    return $result;
                }
                $temps[] = $out;
                // Ham model çıktısı iş diskinde saklanır (sonuç başına son deneme): birleştirme ayarları
                // yeni ücretli çağrı yapmadan yeniden denenebilsin (bkz. mockup_composite.py).
                $disk->put($base->masks['prefix'] . "_raw_{$result->id}.png", (string) file_get_contents($out));
                $temps[] = $composite = $this->tempPath();
                $metrics = $this->vision->composite($basePath, $out, $prefix, $box, $composite, $restore);

                $alignBad = (float) ($metrics['align_mae'] ?? 0) > (float) config('creative.mockup.qa.max_align_mae', 8.0);
                $fidelity = $enabled ? $this->checkFidelity($images[0], $composite, $outfit->region, $view, $temps) : null;
                $failed = $alignBad || ($fidelity['verdict'] ?? null) === 'fail';
                if (! $failed || $attempt >= $max) {
                    break;
                }
                $correction = $this->correctionFrom($fidelity, $alignBad);
            }

            $baseStatus = $base->qa['status'] ?? null;
            $status = match (true) {
                $failed => 'fail',
                in_array($fidelity['verdict'] ?? 'pass', ['warn', 'unknown'], true), $baseStatus === 'warn' => 'warn',
                default => 'pass',
            };

            if ($expectedToken !== null && $result->fresh()?->generation_token !== $expectedToken) {
                return $result;
            }

            $model = $this->generator->modelIdentifier();
            $meta = $result->meta ?? [];
            unset($meta['mockup_run']);
            $result->update([
                'meta'                   => $meta,
                'staged_image_path'      => $this->onModel->persistStaged($composite, $product, $result),
                'status'                 => TryonResult::STATUS_DONE,
                'tryon_driver'           => $model === 'mock' ? 'mock' : 'gemini',
                'tryon_model'            => $model,
                'generation_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error'                  => null,
                'qa'                     => [
                    'status'      => $status,
                    'attempts'    => $attempt + 1,
                    'composite'   => $metrics,
                    'fidelity'    => $fidelity,
                    'view'        => $view,
                    'base_status' => $baseStatus,
                    'base_issues' => array_values(array_filter([
                        ($base->qa['shoes']['ok'] ?? true) ? null : ['detail_tr' => 'Baz görselde ayakkabı görünmüyor (çıplak ayak).'],
                        ...((array) ($base->qa['consistency']['issues'] ?? [])),
                    ])),
                ],
                'review_status'          => TryonResult::REVIEW_PENDING,
                'review_note'            => null,
                'reviewed_by'            => null,
                'reviewed_at'            => null,
            ]);

            $this->notifier->notifyPending($result, $result->created_by);

            return $result;
        } finally {
            ImageFile::delete($temps);
            $this->resolver->cleanup();
        }
    }

    /**
     * Ürün fotoğrafı ↔ birleşik sonuç sadakat kontrolü. Kontrol başarısız olursa üretim durmaz.
     *
     * @param  array<int,string>  $temps
     * @return array{verdict:string, issues:array<int,array<string,mixed>>}
     */
    private function checkFidelity(string $productPhoto, string $composite, string $region, string $view, array &$temps): array
    {
        try {
            $temps[] = $pair = $this->tempPath();
            $this->vision->pair($productPhoto, $composite, $pair);

            return $this->generator->checkFidelity($pair, $region, $view);
        } catch (\Throwable $e) {
            return ['verdict' => 'unknown', 'issues' => [['detail_tr' => 'Sadakat kontrolü yapılamadı: ' . $e->getMessage()]]];
        }
    }

    /** @param array{issues?:array<int,array<string,mixed>>}|null $fidelity */
    private function correctionFrom(?array $fidelity, bool $alignBad): string
    {
        $fixes = collect((array) ($fidelity['issues'] ?? []))
            ->map(fn ($i) => trim((string) ($i['fix_en'] ?? $i['detail_tr'] ?? '')))->filter()->implode(' ');
        if ($alignBad) {
            $fixes .= ' Keep the exact same framing, position and scale as the first image; do not move or resize anything.';
        }

        return trim($fixes);
    }

    /** Python adımının yazacağı geçici dosya yolu (dosya oluşturulmaz). */
    private function tempPath(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/mockup_' . bin2hex(random_bytes(8)) . '.png';
    }
}
