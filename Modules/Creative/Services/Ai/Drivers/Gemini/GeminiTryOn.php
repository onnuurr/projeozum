<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini 3.1 Flash Image ("Nano Banana 2") ile sanal giydirme (try-on) sürücüsü.
 *
 * Posed-manken görseli (kişi) ile ürün görseli (giysi) tek bir generateContent
 * çağrısında birleştirilir; model kişiyi ürünle giydirir. İdm-vton'a göre daha
 * geniş ürün çeşitliliği ve doğal oturma sağlar. Görsel sırası önemlidir:
 * 1) kişi (kimlik/poz/arka plan kaynağı), 2) giysi (yüksek sadakatli ürün).
 */
class GeminiTryOn implements GarmentTryOnContract
{
    public function __construct(
        private GeminiClient $client,
        private GeminiTryOnPromptBuilder $prompts,
    ) {}

    public function tryOn(string $modelImagePath, string $garmentImagePath, array $extraGarmentImages = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string
    {
        $prompt = $this->prompts->build($extraGarmentImages, $extraInstruction, $protectListSentence);

        $images = [$modelImagePath, $garmentImagePath, ...array_column($extraGarmentImages, 'path')];

        // Try-on sabit olarak 3.1-flash-image kullanır ve 1K/2K/4K destekler;
        // bu yüzden kimlik/poz adımlarından ayrı, kendi imageSize env'i vardır.
        $imageConfig = GeminiClient::buildImageConfig(
            (string) config('creative.ai.gemini.image.aspect_ratio', ''),
            (string) config('creative.ai.gemini.image.tryon_size', ''),
        );

        $bytes = $this->client->generateImage(
            $prompt,
            $images,
            (string) config('creative.ai.gemini.tryon_model') ?: null,
            $imageConfig,
        );

        return ImageFile::temp($bytes, 'png');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.ai.gemini.tryon_model');
    }
}
