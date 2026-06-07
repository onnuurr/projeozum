<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\Rendering\RendererContract;
use RuntimeException;

class CreativeRenderService
{
    public function __construct(
        private RendererContract $renderer,
        private CanvasAssetResolver $resolver,
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
     * Tek bir creative_assets satırını render eder, diske yazar ve done işaretler.
     * Hata fırlatırsa çağıran (Job) failed olarak işaretlemekle yükümlüdür.
     */
    public function generate(CreativeAsset $asset): CreativeAsset
    {
        $asset->loadMissing(['template', 'product.images']);

        $template = $asset->template;
        $product  = $asset->product;

        if (! $template || ! $product) {
            throw new RuntimeException('Asset için şablon veya ürün bulunamadı.');
        }

        $imagePaths = [];
        $usedImage  = null;

        if ($src = $this->pickImageSrc($product)) {
            if ($local = $this->resolver->toLocalPath($src)) {
                $imagePaths['product_image'] = $local;
                $usedImage                   = $src;
            }
        }

        try {
            $bytes = $this->renderer->render(
                $template,
                ['product_name' => (string) $product->name],
                $imagePaths,
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

        $asset->fill([
            'image_path'    => $path,
            'status'        => CreativeAsset::STATUS_DONE,
            'review_status' => CreativeAsset::REVIEW_PENDING,
            'error'         => null,
            'meta'          => array_merge($asset->meta ?? [], ['used_image' => $usedImage]),
        ])->save();

        return $asset;
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
