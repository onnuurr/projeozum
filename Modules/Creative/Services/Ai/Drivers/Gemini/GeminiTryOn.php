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

    public function tryOn(string $modelImagePath, string $garmentImagePath): string
    {
        $prompt = $this->prompts->build();

        $bytes = $this->client->generateImage(
            $prompt,
            [$modelImagePath, $garmentImagePath],
            (string) config('creative.ai.gemini.tryon_model') ?: null,
        );

        return ImageFile::temp($bytes, 'png');
    }
}
