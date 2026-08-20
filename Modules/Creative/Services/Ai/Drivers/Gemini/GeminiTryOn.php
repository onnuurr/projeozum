<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Ai\Support\TryonGenerationRendered;

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

    public function tryOn(string $modelImagePath, string $garmentImagePath, array $extraGarmentImages = [], ?string $extraInstruction = null, ?string $protectListSentence = null, ?array $context = null): string
    {
        $prompt = $this->prompts->build($extraGarmentImages, $extraInstruction, $protectListSentence);

        $images = [$modelImagePath, $garmentImagePath, ...array_column($extraGarmentImages, 'path')];

        // Try-on sabit olarak 3.1-flash-image kullanır ve 1K/2K/4K destekler;
        // bu yüzden kimlik/poz adımlarından ayrı, kendi imageSize env'i vardır.
        $imageConfig = GeminiClient::buildImageConfig(
            (string) config('creative.ai.gemini.image.aspect_ratio', ''),
            (string) config('creative.ai.gemini.image.tryon_size', ''),
        );

        $modelName = (string) config('creative.ai.gemini.tryon_model') ?: null;

        $callStartedAt = microtime(true);
        $bytes = $this->client->generateImage($prompt, $images, $modelName, $imageConfig);
        $latencyMs = (int) round((microtime(true) - $callStartedAt) * 1000);

        // Bu, $prompt'un (dinamik slotları dahil nihai hâliyle) ve fiilen gönderilen
        // model/parametrelerin başka HİÇBİR yerde kalıcı olmadığı tek an — creative_tryon_results
        // satırı bir sonraki regenerasyonda yerinde ezilir (bkz. Events/TryonGenerationRendered
        // docblock'u). $context null ise (ör. eski/manuel çağrı yolu) sessizce atlanır.
        if ($context !== null) {
            // Bu event'in listener'ı (Modules/TrainingIngest) kasıtlı olarak senkron
            // çalışır (bkz. CaptureGenerationListener docblock'u — geçici dosyalar
            // finally'de hemen silindiği için kuyruklanamaz). Senkron bir listener'ın
            // hatası burada yutulmazsa asıl giydirme üretimi (zaten tamamlanmış) boşuna
            // kullanıcıya hata olarak yansır — bu event'in tanımı gereği "en iyi çaba"
            // bir telemetri kancasıdır, üretimin başarısını asla etkilemez.
            try {
                event(new TryonGenerationRendered(
                    context: $context,
                    renderedPrompt: $prompt,
                    modelName: $modelName ?? (string) config('creative.ai.gemini.tryon_model'),
                    imageConfig: $imageConfig,
                    latencyMs: $latencyMs,
                    modelImagePath: $modelImagePath,
                    garmentImagePath: $garmentImagePath,
                    extraGarmentImages: $extraGarmentImages,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return ImageFile::temp($bytes, 'png');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.ai.gemini.tryon_model');
    }
}
