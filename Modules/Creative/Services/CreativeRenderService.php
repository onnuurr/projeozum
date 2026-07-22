<?php

namespace Modules\Creative\Services;

use App\Support\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\Ai\AiSceneService;
use Modules\Creative\Services\Ai\SceneRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Creative\Services\Exceptions\PermanentRenderException;
use Modules\Creative\Services\Rendering\RendererContract;

class CreativeRenderService
{
    /** AI compose'a referans olarak verilecek azami ürün görseli sayısı. */
    private const AI_MAX_REFS = 4;

    public function __construct(
        private RendererContract $renderer,
        private CanvasAssetResolver $resolver,
        private BrandTokenService $brandTokens,
        private AiSceneService $aiScenes,
        private CaptionService $captions,
        private CopyService $copy,
        private ImageEnhancerContract $enhancer,
    ) {}

    /**
     * Yüklenen şablon SVG'sini inceleyip boyut + slot tanımlarını doldurur.
     */
    public function inspectTemplate(CreativeTemplate $template): CreativeTemplate
    {
        $absolute = Media::localPath($template->svg_path, $this->disk());
        if ($absolute === null) {
            throw new PermanentRenderException('Şablon SVG bulunamadı: ' . $template->svg_path);
        }
        $meta = $this->renderer->inspect($absolute);

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
        $absolute = Media::localPath($template->svg_path, $this->disk());
        if ($absolute === null) {
            throw new PermanentRenderException('Şablon SVG bulunamadı: ' . $template->svg_path);
        }

        $this->renderer->applySlots($absolute, $slots);

        // Uzak diskte (R2/S3) düzenlenen yerel kopyayı diske geri yükle (oku-değiştir-yaz).
        if (! in_array($this->disk(), ['public', 'local'], true)) {
            Storage::disk($this->disk())->put($template->svg_path, (string) file_get_contents($absolute));
        }

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

        $brand  = $this->brandTokens->tokens();
        $format = $this->resolveFormat($asset);

        $imagePaths = [];
        $usedImage  = null;
        $aiStored   = null;

        if ($src = $this->pickImageSrc($product)) {
            if ($local = $this->resolver->toLocalPath($src)) {
                $usedImage = $src;

                if ($this->wantsAi($asset)) {
                    // Ürünün KENDİ görselleri AI sahnesine konu olarak verilir (kapak + diğerleri).
                    $refs = [$local];
                    foreach ($product->images as $img) {
                        if (count($refs) >= self::AI_MAX_REFS) {
                            break;
                        }
                        if ($img->path === $src || ! $img->path) {
                            continue; // kapak zaten eklendi
                        }
                        if ($p = $this->resolver->toLocalPath($img->path)) {
                            $refs[] = $p;
                        }
                    }

                    $scene = $this->aiScenes->generate(
                        new SceneRequest(
                            productName: (string) $product->name,
                            productImagePath: $local,
                            productImagePaths: $refs,
                            palette: $brand['palette'] ?? [],
                            aspectLabel: $format['aspect'] ?? null,
                            pose: $this->resolvePose($asset),
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

        // Metin slotları: her zaman ürün adı (product_name) hazır; use_copy_ai
        // açıksa şablonun semantik copy slotları (headline/sub/cta) marka tonunda
        // üretilir. Copy üretimi nice-to-have: hata olursa render düşmez.
        $values = ['product_name' => (string) $product->name];
        $copy   = $this->buildCopy($asset, $template, $product, $format);
        $values = array_merge($values, $copy);

        try {
            $bytes = $this->renderer->render(
                $template,
                $values,
                $imagePaths,
                $brand,
                $format['width'] ?? null,
                $format['height'] ?? null,
            );
        } finally {
            $this->resolver->cleanup();
        }

        // Üretim sonrası kalite iyileştirme (upscale + son dokunuş). Kapalıysa/
        // başarısızsa $bytes değişmeden kalır (graceful degrade).
        $tmp      = ImageFile::temp($bytes, 'png');
        $enhanced = $this->enhancer->enhance($tmp);
        $bytes    = (string) file_get_contents($enhanced);
        ImageFile::delete(array_unique([$tmp, $enhanced]));

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
                'copy'       => $copy !== [] ? $copy : null,
                'caption'    => $caption['caption'] ?? null,
                'hashtags'   => $caption['hashtags'] ?? [],
                'render_ms'  => (int) round((microtime(true) - $startedAt) * 1000),
                'format'       => $format['key'] ?? null,
                'format_label' => $format['label'] ?? null,
                'width'        => $format['width'] ?? $template->width,
                'height'       => $format['height'] ?? $template->height,
            ]),
        ])->save();

        return $asset;
    }

    /**
     * use_copy_ai açıksa şablonun copy slotları için marka tonunda metin üretir.
     * Kapalıysa veya üretim başarısız olursa boş dizi döner (metin slotları statik
     * SVG içeriğiyle kalır; render düşmez).
     *
     * Ret sonrası düzeltme talimatı (meta.extra_instructions) varsa marka brief'ine
     * EKLENİR (onu ezmez) — regenerate bu sayede hem markayı hem düzeltmeyi görür.
     *
     * @param  array<string,mixed>  $format
     * @return array<string,string>
     */
    private function buildCopy(CreativeAsset $asset, CreativeTemplate $template, $product, array $format): array
    {
        if (! $this->wantsCopy($asset)) {
            return [];
        }

        try {
            return $this->copy->forTemplate(
                $product,
                $template,
                $format['aspect'] ?? null,
                $this->resolveExtraInstructions($asset),
            );
        } catch (\Throwable $e) {
            Log::warning('Creative copy üretimi başarısız.', [
                'asset_id' => $asset->id ?? null,
                'error'    => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Asset için on-image metin (headline/sub/cta) üretilsin mi? (meta.use_copy_ai)
     */
    private function wantsCopy(CreativeAsset $asset): bool
    {
        return (bool) ($asset->meta['use_copy_ai'] ?? false);
    }

    /**
     * Ret sonrası düzeltme talimatı (meta.extra_instructions). Boşsa null.
     */
    private function resolveExtraInstructions(CreativeAsset $asset): ?string
    {
        $extra = trim((string) ($asset->meta['extra_instructions'] ?? ''));

        return $extra !== '' ? $extra : null;
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
     * Asset'in sosyal medya formatını config'ten çözer (meta.format → varsayılan).
     *
     * @return array{key:?string,width:?int,height:?int,aspect:?string,label:?string}
     */
    private function resolveFormat(CreativeAsset $asset): array
    {
        $formats = (array) config('creative.formats', []);
        $key     = $asset->meta['format'] ?? config('creative.default_format');

        if (! isset($formats[$key])) {
            $key = (string) config('creative.default_format');
        }

        $f = $formats[$key] ?? null;
        if (! is_array($f)) {
            return ['key' => null, 'width' => null, 'height' => null, 'aspect' => null, 'label' => null];
        }

        return [
            'key'    => $key,
            'width'  => (int) $f['width'],
            'height' => (int) $f['height'],
            'aspect' => $f['aspect'] ?? null,
            'label'  => $f['label'] ?? $key,
        ];
    }

    /**
     * Asset, AI sahne/giydirme ile mi üretilmek isteniyor? (meta.use_ai)
     */
    private function wantsAi(CreativeAsset $asset): bool
    {
        return (bool) ($asset->meta['use_ai'] ?? false);
    }

    /**
     * Asset'e özel manken duruşu yönergesini çözer (meta.pose). Boşsa null
     * döner; bu durumda prompt builder ürüne göre kürate poz seçer.
     */
    private function resolvePose(CreativeAsset $asset): ?string
    {
        $pose = trim((string) ($asset->meta['pose'] ?? ''));

        return $pose !== '' ? $pose : null;
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

        return $cover?->path;
    }

    private function disk(): string
    {
        return config('creative.disk', 'public');
    }
}
