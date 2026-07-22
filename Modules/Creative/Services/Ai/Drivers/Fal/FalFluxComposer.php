<?php

namespace Modules\Creative\Services\Ai\Drivers\Fal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\CompositionRequest;
use Modules\Creative\Services\Ai\Contracts\CompositionComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * fal.ai (Black Forest Labs FLUX.2) ile tam-AI post kompozisyonu üretir:
 * ürün + (varsa) manken referansı + marka rengi/ton'u tek bir isteğe
 * gömülüp, üzerinde istenen headline/CTA metni yazılı bitmiş bir görsel
 * ister. ROADMAP.md Faz H/J'de belgelenen risk (typo/ürün sadakati)
 * nedeniyle bu sürücünün çıktısı ASLA doğrudan kabul edilmez — her zaman
 * {@see \Modules\Creative\Services\LayoutConstraintEngine} + OCR gate'inden
 * geçer (bkz. CreativeRenderService::generateAiComposition).
 *
 * NOT — doğrulanmadı: tam model id'si (`creative.composition.fal.model`,
 * ör. `fal-ai/flux-2` veya `fal-ai/flux-pro/kontext`) ve bu modelin çoklu
 * referans görsel + literal metin talimatını hangi alan adlarıyla kabul
 * ettiği fal.ai dokümantasyonundan teyit edilmeden prod'a alınmamalı
 * (bkz. ROADMAP.md Faz J açık sorular).
 */
class FalFluxComposer implements CompositionComposerContract
{
    public function __construct(private FalClient $client) {}

    public function compose(CompositionRequest $request): string
    {
        $input = [
            'prompt'          => $this->buildPrompt($request),
            'image_urls'      => $this->referenceDataUris($request),
            'aspect_ratio'    => $this->aspectRatio($request),
        ];

        Log::info('fal Flux kompozisyon isteği gönderiliyor', [
            'model'           => $this->modelIdentifier(),
            'reference_count' => count($input['image_urls']),
        ]);

        $result = $this->client->run($input, $this->modelIdentifier());

        Log::info('fal Flux kompozisyon yanıtı alındı', [
            'model'       => $this->modelIdentifier(),
            'result_keys' => array_keys($result),
        ]);

        $url = $result['images'][0]['url']
            ?? $result['image']['url']
            ?? null;

        if (! is_string($url) || $url === '') {
            Log::warning('fal Flux kompozisyon sonucunda görsel URL bulunamadı', ['result' => $result]);

            throw new RuntimeException('fal Flux kompozisyon sonucunda görsel URL bulunamadı.');
        }

        return ImageFile::temp($this->fetch($url), 'png');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.composition.fal.model');
    }

    private function buildPrompt(CompositionRequest $request): string
    {
        $parts = [
            sprintf('A professional social media product post for "%s".', $request->productName),
        ];

        if ($request->designBrief) {
            $parts[] = $request->designBrief;
        }

        if ($request->headline) {
            // Kısa/tek-kelimelik başlıkların güvenilir render edildiği manuel
            // testle doğrulandı (bkz. ROADMAP.md Faz J) — uzun/çok kelimeli
            // metin risklidir, bu yüzden LayoutConstraintEngine bunu üretimden
            // ÖNCE (checkIntendedText) sınırlar.
            $parts[] = sprintf(
                'Render the exact headline text "%s" prominently near the top of the frame, spelled correctly, no typos.',
                $request->headline,
            );
        }

        if ($request->subHeadline) {
            $parts[] = sprintf('Include the sub-headline text "%s" below the headline.', $request->subHeadline);
        }

        if ($request->ctaButton) {
            $parts[] = sprintf('Include a call-to-action button reading exactly "%s".', $request->ctaButton);
        }

        if ($request->palette !== []) {
            $parts[] = 'Use a color palette consistent with: ' . implode(', ', array_filter($request->palette)) . '.';
        }

        return implode(' ', $parts);
    }

    /**
     * @return array<int,string>
     */
    private function referenceDataUris(CompositionRequest $request): array
    {
        $refs = [];
        if ($request->mannequinReferencePath && is_file($request->mannequinReferencePath)) {
            $refs[] = ImageFile::dataUri($request->mannequinReferencePath);
        }
        foreach ($request->productImagePaths as $path) {
            if (is_file($path)) {
                $refs[] = ImageFile::dataUri($path);
            }
        }

        return $refs;
    }

    private function aspectRatio(CompositionRequest $request): ?string
    {
        // "4:5 portrait" gibi bir etiketten fal'in beklediği "4:5" biçimine indirger.
        $label = $request->aspectLabel;
        if (! $label) {
            return null;
        }

        [$ratio] = explode(' ', trim($label), 2) + [null];

        return $ratio;
    }

    private function fetch(string $url): string
    {
        if (str_starts_with($url, 'data:')) {
            $comma = strpos($url, ',');
            $bin   = $comma !== false ? base64_decode(substr($url, $comma + 1), true) : false;
            if ($bin === false || $bin === '') {
                throw new RuntimeException('fal data URI çözümlenemedi.');
            }

            return $bin;
        }

        $response = Http::timeout((int) config('creative.ai.timeout', 240))->get($url);
        if ($response->failed()) {
            throw new RuntimeException("fal çıktı görseli indirilemedi (HTTP {$response->status()}).");
        }

        return $response->body();
    }
}
