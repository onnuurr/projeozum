<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\CloseupRequest;
use Modules\Creative\Services\Ai\Contracts\CloseupComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Product\Models\Product;
use RuntimeException;

/**
 * Yakın plan / detay çekimleri: ONAYLI bir tam boy giydirmeden (shot='full') aynı
 * çekimin yakın kadrajlarını üretir (bel üstü, etek ucu, kumaş dokusu...; kadraj
 * kataloğu config('creative.closeup.shots')).
 *
 * Sonuçlar creative_tryon_results'a shot=<anahtar>, source_result_id=<tam boy> olarak
 * yazılır — tam boy giydirmeyle aynı onay → yayın (ProductOnModelService::publish),
 * kapak, silme ve ret sohbeti akışından geçer. Üretim yine staged_image_path'e
 * yazılır; insan onayı olmadan mağazada görünmez.
 */
class ProductCloseupService
{
    public function __construct(
        private CloseupComposerContract $composer,
        private ProductOnModelService $onModel,
        private CanvasAssetResolver $resolver,
        private ImageEnhancerContract $enhancer,
        private ReviewNotifier $notifier,
    ) {}

    /** @return array<string,string> kadraj anahtarı => etiket */
    public static function shotOptions(): array
    {
        return collect((array) config('creative.closeup.shots', []))
            ->map(fn (array $s, string $key) => (string) ($s['label'] ?? $key))
            ->all();
    }

    /**
     * Seçili kadrajlar için yakın plan satırlarını hazırlar (yeniden kuyruğa alma
     * aynı satırı günceller ve onay durumunu sıfırlar — ProductOnModelService::queue ile aynı).
     *
     * @param  array<int,string>  $shots
     * @return Collection<int,TryonResult>
     */
    public function queue(TryonResult $source, array $shots, ?int $creatorId = null): Collection
    {
        if ($source->isCloseup()
            || $source->status !== TryonResult::STATUS_DONE
            || $source->review_status !== TryonResult::REVIEW_APPROVED
            || ! $source->staged_image_path) {
            throw new RuntimeException(__('creative::messages.closeup.source_not_approved'));
        }

        $valid = array_keys(self::shotOptions());

        return collect(array_values(array_unique($shots)))
            ->filter(fn ($shot) => in_array($shot, $valid, true))
            ->map(function (string $shot) use ($source, $creatorId) {
                $existing = TryonResult::query()
                    ->where('product_id', $source->product_id)
                    ->where('pose_id', $source->pose_id)
                    ->where('shot', $shot)
                    ->where('pipeline', $source->pipeline ?? TryonResult::PIPELINE_LEGACY)
                    ->first();

                // Sohbetten gelen düzeltme talimatı (extra_instructions) korunur; giysi
                // detay görselleri kaynak giydirmeninkiyle eşitlenir.
                $meta = $existing?->meta ?? [];
                if (! empty($source->meta['garment_extras'])) {
                    $meta['garment_extras'] = $source->meta['garment_extras'];
                } else {
                    unset($meta['garment_extras']);
                }

                return TryonResult::updateOrCreate(
                    ['product_id' => $source->product_id, 'pose_id' => $source->pose_id, 'shot' => $shot, 'pipeline' => $source->pipeline ?? TryonResult::PIPELINE_LEGACY],
                    [
                        'mannequin_id'       => $source->mannequin_id,
                        'source_result_id'   => $source->id,
                        'garment_image_path' => $source->garment_image_path,
                        'status'             => TryonResult::STATUS_QUEUED,
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
            })
            ->values();
    }

    /**
     * Tek bir yakın planı üretir ve staged_image_path'e yazar. $expectedToken
     * eşleşmiyorsa (arada yeniden kuyruğa alınmış) sonuç DB'ye yazılmaz.
     */
    public function generate(TryonResult $result, ?string $expectedToken = null): TryonResult
    {
        $product = $result->product()->with('images')->first();
        $source  = $result->sourceResult;
        $shot    = config("creative.closeup.shots.{$result->shot}");

        if (! $product) {
            throw new RuntimeException(__('creative::messages.tryon.product_not_found'));
        }
        if (! is_array($shot) || empty($shot['prompt'])) {
            throw new RuntimeException(__('creative::messages.closeup.unknown_shot', ['shot' => $result->shot]));
        }
        if (! $source || ! $source->staged_image_path) {
            throw new RuntimeException(__('creative::messages.closeup.source_missing'));
        }

        $preparedTemps = [];
        $out = null;
        $enhanced = null;
        $startedAt = microtime(true);

        try {
            $sourcePath = $this->resolver->toLocalPath($source->staged_image_path);
            if (! $sourcePath) {
                throw new RuntimeException(__('creative::messages.closeup.source_missing'));
            }

            $garmentSrc = $result->garment_image_path ?: $this->onModel->pickPlainProductPhoto($product);
            $garmentPath = $garmentSrc ? $this->resolver->toLocalPath($garmentSrc) : null;
            if (! $garmentPath) {
                throw new RuntimeException(__('creative::messages.tryon.product_has_no_image'));
            }
            $garmentPath = $this->onModel->prepareGarmentImage($garmentPath, $preparedTemps);

            $extras = [];
            foreach ((array) ($result->meta['garment_extras'] ?? []) as $extra) {
                $extraPath = ! empty($extra['path']) ? $this->resolver->toLocalPath($extra['path']) : null;
                if ($extraPath) {
                    $extras[] = [
                        'path'  => $this->onModel->prepareGarmentImage($extraPath, $preparedTemps),
                        'label' => $extra['label'] ?? null,
                    ];
                }
            }

            $out = $this->composer->compose(new CloseupRequest(
                sourceImagePath:    $sourcePath,
                garmentImagePath:   $garmentPath,
                shotPrompt:         (string) $shot['prompt'],
                extraGarmentImages: $extras,
                extraInstruction:   trim((string) ($result->meta['extra_instructions'] ?? '')) ?: null,
            ));

            $enhanced = $this->enhancer->enhance($out);

            if ($expectedToken !== null && $result->fresh()?->generation_token !== $expectedToken) {
                return $result;
            }

            $result->update([
                'staged_image_path'      => $this->onModel->persistStaged($enhanced, $product, $result),
                'status'                 => TryonResult::STATUS_DONE,
                'tryon_driver'           => $this->composer->modelIdentifier() === 'mock' ? 'mock' : 'gemini',
                'tryon_model'            => $this->composer->modelIdentifier(),
                'generation_duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error'                  => null,
                'review_status'          => TryonResult::REVIEW_PENDING,
                'review_note'            => null,
                'reviewed_by'            => null,
                'reviewed_at'            => null,
            ]);

            $this->notifier->notifyPending($result, $result->created_by);

            return $result;
        } finally {
            ImageFile::delete(array_filter([$out, $enhanced, ...$preparedTemps]));
            $this->resolver->cleanup();
        }
    }
}
