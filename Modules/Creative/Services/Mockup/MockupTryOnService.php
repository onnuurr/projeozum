<?php

namespace Modules\Creative\Services\Mockup;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
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
            unset($meta['extra_instructions']);           // yeni baz → eski düzeltme talimatı geçersiz

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

            $temps[] = $out = $this->generator->tryOn(
                $crop, $images, $outfit->region, $outfit->length,
                trim((string) ($result->meta['extra_instructions'] ?? '')) ?: null,
            );

            $temps[] = $composite = $this->tempPath();
            $restore = (bool) ($base->masks['long'] ?? false) && $outfit->region !== 'top';
            $metrics = $this->vision->composite($basePath, $out, $prefix, $box, $composite, $restore);

            if ($expectedToken !== null && $result->fresh()?->generation_token !== $expectedToken) {
                return $result;
            }

            $model = $this->generator->modelIdentifier();
            $result->update([
                'staged_image_path'      => $this->onModel->persistStaged($composite, $product, $result),
                'status'                 => TryonResult::STATUS_DONE,
                'tryon_driver'           => $model === 'mock' ? 'mock' : 'gemini',
                'tryon_model'            => $model,
                'generation_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error'                  => null,
                'qa'                     => ['composite' => $metrics],
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

    /** Python adımının yazacağı geçici dosya yolu (dosya oluşturulmaz). */
    private function tempPath(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/mockup_' . bin2hex(random_bytes(8)) . '.png';
    }
}
