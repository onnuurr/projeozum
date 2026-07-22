<?php

namespace Modules\Creative\Services;

use App\Support\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\Ai\AiSceneService;
use Modules\Creative\Services\Ai\CompositionRequest;
use Modules\Creative\Services\Ai\Contracts\CompositionComposerContract;
use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\CopyRequest;
use Modules\Creative\Services\Ai\SceneRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use Modules\Creative\Services\Exceptions\CompositionConstraintException;
use Modules\Creative\Services\Exceptions\PermanentRenderException;
use Modules\Creative\Services\Rendering\RendererContract;
use Modules\Creative\Services\Vision\TextRecognizerContract;
use Modules\Product\Models\Product;

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
        private ImageEnhancerContract $enhancer,
        private CopyGeneratorContract $copyGenerator,
        private CreativeCopyRuleEngine $copyRules,
        private ReviewNotifier $notifier,
        private CompositionComposerContract $composer,
        private TextRecognizerContract $textRecognizer,
        private LayoutConstraintEngine $layoutConstraints,
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

        // Ret sonrası chatbot ile üretilen düzeltme talimatı varsa (bkz. ReviewChatService),
        // marka brief'ine EK olarak eklenir — yapısal alanları (ürün, şablon) ezmez. Hem sahne
        // hem metin üretimi bunu görür (ret sebebi hangisiyle ilgili olursa olsun).
        $extra          = trim((string) ($asset->meta['extra_instructions'] ?? ''));
        $briefWithExtra = $this->joinBrief($brand['criteria']['design_brief'] ?? null, $extra);

        // İkinci, opsiyonel render motoru (Faz J): fal.ai Flux ile tam-AI post
        // kompozisyonu. SVG yolu (aşağısı) hiç değişmeden kalır.
        if (($asset->meta['render_engine'] ?? 'svg') === 'ai_compose') {
            return $this->generateAiComposition($asset, $product, $template, $brand, $format, $briefWithExtra, $startedAt);
        }

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
                            designBrief: $briefWithExtra !== '' ? $briefWithExtra : null,
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

        $copy   = $this->buildCopy($asset, $product, $template, $brand);
        $values = array_merge(['product_name' => (string) $product->name], $copy['values'] ?? []);

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
                'caption'    => $caption['caption'] ?? null,
                'hashtags'   => $caption['hashtags'] ?? [],
                'render_ms'  => (int) round((microtime(true) - $startedAt) * 1000),
                'format'       => $format['key'] ?? null,
                'format_label' => $format['label'] ?? null,
                'width'        => $format['width'] ?? $template->width,
                'height'       => $format['height'] ?? $template->height,
                'copy'        => array_filter($copy['values'] ?? [], fn ($v) => $v !== '') ?: null,
                'copy_ai_raw' => $copy['envelope'] ?? null,
            ]),
        ])->save();

        $this->notifier->notifyPending($asset, $asset->created_by);

        return $asset;
    }

    /**
     * AI kompozisyon (fal.ai Flux) render motoru — Faz J. Ürün + marka
     * kriterlerini tek bir istekte bitmiş post olarak ister, sonra
     * LayoutConstraintEngine + OCR ile doğrular. Doğrulama başarısız olursa
     * CompositionConstraintException fırlatır (GenerateCreativeJob bunu
     * mevcut tries/backoff ile retry eder) — insan reviewer'a asla
     * doğrulanmamış bir kompozisyon ulaşmaz.
     */
    private function generateAiComposition(
        CreativeAsset $asset,
        Product $product,
        CreativeTemplate $template,
        array $brand,
        array $format,
        string $briefWithExtra,
        float $startedAt,
    ): CreativeAsset {
        if (! config('creative.composition.ocr.enabled')) {
            // Retry ile çözülmez — bu bir config/operasyon hatası: OCR'sız
            // tam-AI render'ı "ham AI çıktısı asla doğrudan kullanılmaz"
            // disiplinini ihlal eder.
            throw new PermanentRenderException(
                'AI kompozisyon motoru (ai_compose) OCR doğrulaması olmadan çalıştırılamaz. '
                . 'creative.composition.ocr.enabled=true yapın.',
            );
        }

        $refs = [];
        if ($src = $this->pickImageSrc($product)) {
            if ($local = $this->resolver->toLocalPath($src)) {
                $refs[] = $local;
                foreach ($product->images as $img) {
                    if (count($refs) >= self::AI_MAX_REFS) {
                        break;
                    }
                    if ($img->path === $src || ! $img->path) {
                        continue;
                    }
                    if ($p = $this->resolver->toLocalPath($img->path)) {
                        $refs[] = $p;
                    }
                }
            }
        }

        $copy    = $this->buildCopy($asset, $product, $template, $brand, force: true);
        $values  = $copy['values'] ?? [];
        $wantCta = collect($template->slots ?? [])->contains(fn ($s) => ($s['key'] ?? null) === 'cta_button');

        // Fail-fast: render'dan ÖNCE ucuz/I-O'suz doğrulama, boşuna bir AI
        // çağrısı yapılmasın.
        $preCheck = $this->layoutConstraints->checkIntendedText($values);
        if (! $preCheck['passed']) {
            throw new CompositionConstraintException(implode('; ', $preCheck['violations']));
        }

        try {
            $composedPath = $this->composer->compose(new CompositionRequest(
                productName: (string) $product->name,
                productImagePaths: $refs,
                palette: $brand['palette'] ?? [],
                aspectLabel: $format['aspect'] ?? null,
                headline: $values['headline'] ?? null,
                subHeadline: $values['sub_headline'] ?? null,
                ctaButton: $values['cta_button'] ?? null,
                designBrief: $briefWithExtra !== '' ? $briefWithExtra : null,
            ));
        } finally {
            $this->resolver->cleanup();
        }

        $ocrWords = $this->textRecognizer->recognize($composedPath);
        $report   = $this->layoutConstraints->evaluate($ocrWords, $values, $format, $wantCta);

        if (! $report['passed']) {
            ImageFile::delete([$composedPath]);
            throw new CompositionConstraintException(implode('; ', $report['violations']));
        }

        $enhanced = $this->enhancer->enhance($composedPath);
        $bytes    = (string) file_get_contents($enhanced);
        ImageFile::delete(array_unique([$composedPath, $enhanced]));

        $path = sprintf(
            '%s/%d/%d.png',
            config('creative.output_dir', 'creatives'),
            $product->id,
            $asset->id,
        );

        Storage::disk($this->disk())->put($path, $bytes);

        $caption = $this->buildCaption($product);

        $asset->fill([
            'image_path'    => $path,
            'status'        => CreativeAsset::STATUS_DONE,
            'review_status' => CreativeAsset::REVIEW_PENDING,
            'error'         => null,
            'meta'          => array_merge($asset->meta ?? [], [
                'render_engine'      => 'ai_compose',
                'composition_model'  => $this->composer->modelIdentifier(),
                'constraint_report'  => $report,
                'intended_text'      => $values,
                'caption'      => $caption['caption'] ?? null,
                'hashtags'     => $caption['hashtags'] ?? [],
                'render_ms'    => (int) round((microtime(true) - $startedAt) * 1000),
                'format'       => $format['key'] ?? null,
                'format_label' => $format['label'] ?? null,
                'width'        => $format['width'] ?? $template->width,
                'height'       => $format['height'] ?? $template->height,
                'copy_ai_raw'  => $copy['envelope'] ?? null,
            ]),
        ])->save();

        $this->notifier->notifyPending($asset, $asset->created_by);

        return $asset;
    }

    /**
     * Şablonun tanımladığı headline/sub_headline/cta_button slotları için
     * (varsa) AI ile marka kriterlerine uygun metin üretir. Şablon bu
     * slotları hiç tanımlamıyorsa maliyetsiz no-op döner. AI kapalıysa ya da
     * başarısız olursa slotlar boş string ile doldurulur — TANIMLI bir slotu
     * `values`'a hiç yazmamak, render motorunda literal yer tutucu metin
     * ("headline" gibi) sızmasına yol açar (bkz. apply_slots.py/render.py).
     *
     * @return array{values:array<string,string>,envelope:?array}
     */
    private function buildCopy(CreativeAsset $asset, Product $product, CreativeTemplate $template, array $brand, bool $force = false): array
    {
        $slotsByKey = collect($template->slots ?? [])
            ->filter(fn ($s) => in_array($s['key'] ?? null, ['headline', 'sub_headline', 'cta_button'], true))
            ->keyBy('key')->all();

        if ($slotsByKey === []) {
            return [];
        }

        $resolved = ['headline' => null, 'sub_headline' => null, 'cta_button' => null];
        $envelope = null;

        // $force: ai_compose motorunun SVG fallback'i yok — metin slotu
        // tanımlıysa AI'dan metin istemek isteğe bağlı değil (bkz. generateAiComposition).
        if ($force || (bool) ($asset->meta['use_copy_ai'] ?? false)) {
            try {
                $product->loadMissing('category');

                // Ret sonrası chatbot düzeltme talimatı varsa brief'e ek olarak eklenir
                // (bkz. generate() — aynı hesap, buildCopy() self-contained kalsın diye burada tekrarlanır).
                $extra          = trim((string) ($asset->meta['extra_instructions'] ?? ''));
                $briefWithExtra = $this->joinBrief($brand['criteria']['design_brief'] ?? null, $extra);

                $request = new CopyRequest(
                    productName: (string) $product->name,
                    category: $product->category?->name,
                    attributes: array_filter(
                        ['materyal' => $product->material, 'cinsiyet' => $product->gender],
                        fn ($v) => is_string($v) && $v !== '',
                    ),
                    designBrief: $briefWithExtra !== '' ? $briefWithExtra : null,
                    tone: $brand['criteria']['tone'] ?? null,
                    ctaPhrases: $brand['criteria']['cta_phrases'] ?? [],
                    bannedWords: $brand['criteria']['banned_words'] ?? [],
                );
                $envelope = $this->copyGenerator->generate($request);
                $resolved = $this->copyRules->resolve($envelope['data'] ?? [], $brand['criteria'] ?? [], $slotsByKey);
            } catch (\Throwable $e) {
                Log::warning('Creative AI copywriting başarısız.', [
                    'product_id' => $product->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $out = [];
        foreach (array_keys($slotsByKey) as $key) {
            $out[$key] = (string) ($resolved[$key] ?? '');
        }

        return ['values' => $out, 'envelope' => $envelope];
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
     * Marka brief'i + (varsa) chatbot düzeltme talimatını tek metne birleştirir.
     * Boş parçalar atlanır — aksi halde brief boşken "trim(''.'. '.extra)" gibi
     * naif bir concat, başında sarkan bir ". " bırakırdı.
     */
    private function joinBrief(?string $brief, string $extra): string
    {
        $parts = array_filter([trim((string) $brief), $extra], fn ($p) => $p !== '');

        return implode('. ', $parts);
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
