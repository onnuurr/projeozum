<?php

namespace Modules\Creative\Services\Mockup;

use App\Support\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\CanvasAssetResolver;
use Modules\Creative\Services\ProductOnModelService;
use Modules\Product\Events\ProductImagesChanged;
use Modules\Product\Models\ProductImage;
use RuntimeException;

/**
 * Köşe detayı (2026-10-10): yakın plan ayrı bir manken pozu değil — kombinin ilk ek pozunun (galerideki
 * ikinci görsel) bir köşesine ürün fotoğrafından yuvarlak detay işlenir. Detay GERÇEK ürün fotoğrafından
 * (kumaş/desen birebir); hangi fotoğraf + hangi bölge AI ile bulunur (tek analiz çağrısı, aynı ürün+rengin
 * diğer beden gruplarında yeniden kullanılır), onaycı kartta kutuyu düzeltebilir ya da detayı kaldırabilir.
 *
 * Detaysız (temiz) birleşik görsel iş diskinde saklanır: kutu değişince oradan ücretsiz yeniden çizilir.
 * meta.detail_inset = {photo, box:[x0,y0,x1,y1] (0..1), label, source: ai|manual} | {off: true}.
 */
class MockupDetailInset
{
    public function __construct(
        private MockupGeneratorContract $generator,
        private MockupVisionContract $vision,
        private CanvasAssetResolver $resolver,
        private ProductOnModelService $onModel,
    ) {}

    public function isTarget(TryonResult $result, ?MockupBase $base): bool
    {
        if (! config('creative.mockup.detail_inset.enabled', true) || ! $base || ! $result->isMockup() || $result->isCloseup()) {
            return false;
        }
        $first = ((array) $base->outfit?->poses)[0] ?? null;

        return $first !== null && $base->pose?->pose_key === $first;
    }

    /**
     * Giydirme sonucuna köşe detayı işler. Hiçbir hata üretimi durdurmaz: işlenemezse görsel detaysız döner.
     *
     * @param  array<int,string>  $temps
     * @return array{0:string, 1:?array<string,mixed>} [görsel yolu, meta.detail_inset]
     */
    public function apply(TryonResult $result, MockupBase $base, string $composite, array &$temps): array
    {
        if (! $this->isTarget($result, $base)) {
            return [$composite, null];
        }
        $this->disk()->put($this->cleanPath($base, $result), (string) file_get_contents($composite));

        try {
            $spec = $this->spec($result);
            if ($spec['off'] ?? false) {
                return [$composite, $spec];
            }
            $temps[] = $out = $this->tempPath();
            $this->render($base, $composite, $spec, $out);

            return [$out, $spec];
        } catch (\Throwable $e) {
            Log::warning('Mokap köşe detayı işlenemedi', ['result_id' => $result->id, 'error' => $e->getMessage()]);

            return [$composite, null];
        } finally {
            $this->resolver->cleanup();
        }
    }

    /**
     * Onaycının düzeltmesi: yeni fotoğraf + kutu ya da $box = null → detayı kaldır. Temiz görselden ücretsiz
     * yeniden çizilir. Yayınlanmış görsel aynı dosyayı paylaştığı için mağaza senkronu tetiklenir.
     *
     * @param  array{0:float,1:float,2:float,3:float}|null  $box
     */
    public function update(TryonResult $result, ?string $photo, ?array $box): TryonResult
    {
        $base = $result->mockupBase()->with(['outfit', 'pose'])->first();
        $clean = $base ? $this->cleanPath($base, $result) : null;
        if (! $base || ! $clean || ! $this->disk()->exists($clean)) {
            throw new RuntimeException('Bu görselin detaysız hali bulunamadı; yeniden üretin.');
        }
        $prev = (array) ($result->meta['detail_inset'] ?? []);
        if ($box !== null && ! in_array($photo, $this->candidates($result), true)) {
            throw new RuntimeException('Seçilen fotoğraf bu ürünün fotoğrafı değil.');
        }
        $spec = $box === null ? ['off' => true]
            : ['photo' => $photo, 'box' => array_map('floatval', $box), 'label' => $prev['label'] ?? null, 'source' => 'manual'];

        $temps = [];
        try {
            $path = $this->disk()->path($clean);
            if ($box !== null) {
                $temps[] = $path = $this->tempPath();
                $this->render($base, $this->disk()->path($clean), $spec, $path);
            }
            $result->update([
                'staged_image_path' => $this->onModel->persistStaged($path, $result->product, $result),
                'meta'              => [...($result->meta ?? []), 'detail_inset' => $spec],
            ]);
        } finally {
            foreach ($temps as $t) {
                @unlink($t);
            }
            $this->resolver->cleanup();
        }
        if ($result->product_image_id) {
            ProductImagesChanged::dispatch($result->product_id);
        }

        return $result->fresh();
    }

    /**
     * Detay için kullanılabilecek ürün fotoğrafları (mokap çıktıları hariç, rengin ya da genel), sıralı.
     *
     * @return list<string>
     */
    public function candidates(TryonResult $result): array
    {
        $color = trim((string) $result->color);

        return ProductImage::query()->where('product_id', $result->product_id)->orderByDesc('is_cover')->orderBy('sort_order')->get()
            ->reject(fn (ProductImage $i) => str_contains((string) $i->path, '/onmodel_'))
            ->filter(fn (ProductImage $i) => $color === '' || empty($i->variant_colors) || in_array($color, (array) $i->variant_colors, true))
            ->pluck('path')->take((int) config('creative.mockup.detail_inset.max_photos', 4))->values()->all();
    }

    /** Kartta gösterilecek düzenleme verisi; hedef değilse null. */
    public function present(TryonResult $result): ?array
    {
        if (! array_key_exists('detail_inset', (array) $result->meta)) {
            return null;
        }
        $spec = (array) $result->meta['detail_inset'];

        return [
            'off'    => (bool) ($spec['off'] ?? false),
            'photo'  => $spec['photo'] ?? null,
            'box'    => $spec['box'] ?? null,
            'label'  => $spec['label'] ?? null,
            'source' => $spec['source'] ?? null,
            'photos' => array_map(fn ($p) => ['path' => $p, 'url' => Media::url($p)], $this->candidates($result)),
        ];
    }

    /** Önceki karar (elle düzeltme / kaldırma korunur) → aynı ürün+rengin başka setindeki karar → AI. */
    private function spec(TryonResult $result): array
    {
        $candidates = $this->candidates($result);
        $usable = fn ($s) => is_array($s) && (($s['off'] ?? false) || in_array($s['photo'] ?? null, $candidates, true));

        $prev = $result->meta['detail_inset'] ?? null;
        if ($usable($prev)) {
            return $prev;
        }
        $sibling = TryonResult::query()->where('product_id', $result->product_id)->where('color', (string) $result->color)
            ->where('pipeline', TryonResult::PIPELINE_MOCKUP)->whereKeyNot($result->id)->whereNotNull('meta->detail_inset')
            ->latest('id')->get()->map(fn ($r) => $r->meta['detail_inset'] ?? null)
            ->first(fn ($s) => $usable($s) && ! ($s['off'] ?? false));
        if ($sibling) {
            return $sibling;
        }
        if ($candidates === []) {
            throw new RuntimeException('Ürün fotoğrafı yok.');
        }

        $local = array_map(fn ($p) => $this->resolver->toLocalPath($p) ?? throw new RuntimeException("Fotoğraf okunamadı: {$p}"), $candidates);
        $found = $this->generator->detailBox($local);

        return ['photo' => $candidates[$found['photo']], 'box' => $found['box'], 'label' => $found['label_tr'] ?: null, 'source' => 'ai'];
    }

    private function render(MockupBase $base, string $composite, array $spec, string $out): void
    {
        $photo = $this->resolver->toLocalPath((string) $spec['photo']) ?? throw new RuntimeException('Fotoğraf okunamadı.');
        $this->vision->inset($composite, $photo, $spec['box'], $this->disk()->path($base->masks['prefix'] . '_labels.npy'), $out);
    }

    private function cleanPath(MockupBase $base, TryonResult $result): string
    {
        return $base->masks['prefix'] . "_clean_{$result->id}.png";
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('creative.mockup.work_disk', 'local'));
    }

    private function tempPath(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/mockup_inset_' . bin2hex(random_bytes(8)) . '.png';
    }
}
