<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\Ai\AiSceneService;
use Modules\Creative\Services\Ai\SceneRequest;
use Modules\Creative\Services\Exceptions\PermanentRenderException;
use Modules\Creative\Services\Rendering\RendererContract;

class CreativeRenderService
{
    public function __construct(
        private RendererContract $renderer,
        private CanvasAssetResolver $resolver,
        private BrandTokenService $brandTokens,
        private AiSceneService $aiScenes,
        private CaptionService $captions,
    ) {}

    /**
     * Yüklenen şablon SVG'sini inceleyip boyut + slot tanımlarını doldurur.
     */
    public function inspectTemplate(CreativeTemplate $template): CreativeTemplate
    {
        $absolute = Storage::disk($this->disk())->path($template->svg_path);
        $meta     = $this->renderer->inspect($absolute);

        $template->fill([
            'width'  => $meta['width'],
            'height' => $meta['height'],
            'slots'  => $meta['slots'],
        ])->save();

        return $template;
    }

    /**
     * Tasarımcıdan gelen slotları SVG'ye yazar, ardından şablonu yeniden
     * inceleyerek DB'deki slot/boyut kopyasını tazeler.
     *
     * @param  array<int,array<string,mixed>>  $slots
     */
    public function applyTemplateSlots(CreativeTemplate $template, array $slots): CreativeTemplate
    {
        $absolute = Storage::disk($this->disk())->path($template->svg_path);

        $this->renderer->applySlots($absolute, $slots);

        return $this->inspectTemplate($template);
    }

    /**
     * Tek bir creative_assets satırını render eder, diske yazar ve done işaretler.
     * Hata fırlatırsa çağıran (Job) failed olarak işaretlemekle yükümlüdür.
     */
    public function generate(CreativeAsset $asset): CreativeAsset
    {
        $startedAt = microtime(true);

        $asset->loadMissing(['template', 'product.images']);

        $template = $asset->template;
        $product  = $asset->product;

        if (! $template || ! $product) {
            // Kalıcı hata: retry anlamsız (asset'in şablonu/ürünü yok).
            throw new PermanentRenderException('Asset için şablon veya ürün bulunamadı.');
        }

        $brand = $this->brandTokens->tokens();

        $imagePaths = [];
        $usedImage  = null;
        $aiStored   = null;

        if ($src = $this->pickImageSrc($product)) {
            if ($local = $this->resolver->toLocalPath($src)) {
                $usedImage = $src;

                if ($this->wantsAi($asset)) {
                    // Ham ürün fotoğrafı yerine AI ile kurgulanmış/giydirilmiş sahne.
                    $scene = $this->aiScenes->generate(
                        new SceneRequest(
                            productName: (string) $product->name,
                            productImagePath: $local,
                            palette: $brand['palette'] ?? [],
                        ),
                        (int) $product->id,
                    );
                    $imagePaths['product_image'] = $scene['path'];
                    $aiStored                    = $scene['stored'];
                } else {
                    $imagePaths['product_image'] = $local;
                }
            }
        }

        try {
            $bytes = $this->renderer->render(
                $template,
                ['product_name' => (string) $product->name],
                $imagePaths,
                $brand,
            );
        } finally {
            $this->resolver->cleanup();
        }

        $path = sprintf(
            '%s/%d/%d.png',
            config('creative.output_dir', 'creatives'),
            $product->id,
            $asset->id,
        );

        Storage::disk($this->disk())->put($path, $bytes);

        // Caption nice-to-have: üretimi başarısız olursa render'ı düşürme.
        $caption = $this->buildCaption($product);

        $asset->fill([
            'image_path'    => $path,
            'status'        => CreativeAsset::STATUS_DONE,
            'review_status' => CreativeAsset::REVIEW_PENDING,
            'error'         => null,
            'meta'          => array_merge($asset->meta ?? [], [
                'used_image' => $usedImage,
                'ai_scene'   => $aiStored,
                'caption'    => $caption['caption'] ?? null,
                'hashtags'   => $caption['hashtags'] ?? [],
                'render_ms'  => (int) round((microtime(true) - $startedAt) * 1000),
            ]),
        ])->save();

        return $asset;
    }

    /**
     * Render başarılıysa marka tonunda caption üretir. Hata olursa null döner
     * (caption opsiyonel; asset yine de 'done' işaretlenir).
     *
     * @return array{caption:?string,hashtags:array<int,string>}
     */
    private function buildCaption($product): array
    {
        try {
            return $this->captions->forProduct($product);
        } catch (\Throwable $e) {
            Log::warning('Creative caption üretimi başarısız.', [
                'product_id' => $product->id ?? null,
                'error'      => $e->getMessage(),
            ]);

            return ['caption' => null, 'hashtags' => []];
        }
    }

    /**
     * Asset, AI sahne/giydirme ile mi üretilmek isteniyor? (meta.use_ai)
     */
    private function wantsAi(CreativeAsset $asset): bool
    {
        return (bool) ($asset->meta['use_ai'] ?? false);
    }

    /**
     * Kapak görselini, yoksa ilk görseli seçer.
     */
    private function pickImageSrc($product): ?string
    {
        $images = $product->images;
        if ($images->isEmpty()) {
            return null;
        }

        $cover = $images->firstWhere('is_cover', true) ?? $images->first();

        return $cover?->url;
    }

    private function disk(): string
    {
        return config('creative.disk', 'public');
    }
}
