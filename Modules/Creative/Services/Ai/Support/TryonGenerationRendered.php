<?php

namespace Modules\Creative\Services\Ai\Support;

/**
 * Bir giydirme üretiminin Gemini'ye fiilen gönderildiği an — nihai (dinamik
 * slotları doldurulmuş) prompt metni, model adı ve girdi görselleri bu anda
 * hâlâ bellekte, hiçbir yerde kalıcı değil (bkz. GeminiTryOn::tryOn). Bu event
 * olmadan Modules/TrainingIngest bu veriyi asla göremez: creative_tryon_results
 * satırı bir sonraki yeniden üretimde yerinde ezilir.
 *
 * Services/Ai/Support altında (Domain'de DEĞİL) kasıtlı — bu sınıf, AI çağrısının
 * kendisiyle ilgili bir telemetri/değer nesnesi (tıpkı CaptionRequest gibi), Creative
 * domain mantığı değil. deptrac.php, Services/Ai/(Contracts|Drivers|Support)/'u ayrı,
 * bağımsız bir "AiInfra" katmanı sayar; GeminiTryOn (aynı katman) bu yüzden buna
 * bağımlı olabilir ama Modules/Creative/Events/ (Domain katmanı) içindeki hiçbir şeye
 * OLAMAZ (bkz. deptrac.php: AI infra paylaşılır, domain mantığı asla). Yalnızca
 * Modules/Creative içinden fırlatılır; bu modül TrainingIngest'i TANIMAZ (module.json
 * bağımlılığı tek yönlü, TrainingIngest -> Creative).
 */
class TryonGenerationRendered
{
    /**
     * @param  array{result_id:int,generation_token:?string,product_id:int,pose_id:int,mannequin_id:int,requested_at:\DateTimeInterface}  $context
     * @param  array<string,mixed>  $imageConfig  GeminiClient::buildImageConfig() çıktısı (aspectRatio/imageSize varsa)
     * @param  array<int,array{path:string,label?:?string}>  $extraGarmentImages  Ana giysi görseli dışındaki detay referansları
     */
    public function __construct(
        public readonly array $context,
        public readonly string $renderedPrompt,
        public readonly string $modelName,
        public readonly array $imageConfig,
        public readonly int $latencyMs,
        public readonly string $modelImagePath,
        public readonly string $garmentImagePath,
        public readonly array $extraGarmentImages = [],
    ) {}
}
