<?php

namespace Modules\TrainingIngest\Listeners;

use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\Pose;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder;
use Modules\Creative\Services\Ai\Support\TryonGenerationRendered;
use Modules\Product\Models\Product;
use Modules\TrainingIngest\Exceptions\IngestionValidationException;
use Modules\TrainingIngest\Models\GenerationInput;
use Modules\TrainingIngest\Services\IngestionService;

/**
 * Bir giydirme üretiminin nihai (dinamik slotları doldurulmuş) prompt'unun Gemini'ye
 * gönderildiği andaki tek kalıcı yakalama noktası — bkz. TryonGenerationRendered
 * docblock'u.
 *
 * BİLEREK KUYRUKSUZ (senkron): $event->modelImagePath/garmentImagePath/extraGarmentImages
 * ImageFile::temp() ile oluşturulmuş GEÇİCİ dosyalardır — ProductOnModelService::generate()
 * kendi finally bloğunda üretim tamamen bitince bu dosyaları hemen siler. Bu listener
 * kuyruklu olsaydı (tek worker, sıralı işleniyor), üretim job'ı bitip finally çalıştıktan
 * SONRA sıradaki iş olarak çalışacağı için dosyalar %100 ihtimalle zaten silinmiş olurdu
 * (2026-08-20: canlıda gerçekten böyle oldu, dosyalar bulunamadı hatasıyla sürekli retry
 * edip failed_jobs'a düştü). Senkron çalışmak kullanıcıyı BLOKLAMAZ — zaten çağıran
 * GeminiTryOn::tryOn() → ProductOnModelService::generate() zinciri GenerateOnModelJob
 * içinde, kullanıcının HTTP isteğinden bağımsız arka planda çalışıyor. Bu listener'ın
 * hata fırlatması ana üretimi bozmasın diye event() çağrısı GeminiTryOn'da try/catch
 * içine alınmıştır.
 */
class CaptureGenerationListener
{
    public function __construct(private readonly IngestionService $ingestion) {}

    public function handle(TryonGenerationRendered $event): void
    {
        if (! config('training-ingest.enabled')) {
            // training_writer/training_reader rolleri ve R2 bucket'ı kurulana kadar
            // kapalı — bkz. Modules/TrainingIngest/config/config.php.
            return;
        }

        $context = $event->context;
        $sourceGenerationId = $context['generation_token'] ?? null;

        if (! is_string($sourceGenerationId) || $sourceGenerationId === '') {
            // generation_token yoksa (eski/manuel çağrı yolu) hiçbir korelasyon
            // anahtarı kurulamaz.
            throw new IngestionValidationException(
                'TryonGenerationRendered: context.generation_token boş, üretim yakalanamıyor.'
            );
        }

        $mannequin = Mannequin::find($context['mannequin_id']);
        $pose = Pose::find($context['pose_id']);
        $product = Product::find($context['product_id']);

        if (! $mannequin || ! $pose || ! $product) {
            // SaaS tarafında satır silinmiş/taşınmış olabilir.
            throw new IngestionValidationException(
                "TryonGenerationRendered: mannequin/pose/product bulunamadı ({$sourceGenerationId})."
            );
        }

        $orderedInputs = [
            ['local_path' => $event->modelImagePath, 'kind' => GenerationInput::KIND_MANNEQUIN],
            ['local_path' => $event->garmentImagePath, 'kind' => GenerationInput::KIND_FRONT],
        ];
        foreach ($event->extraGarmentImages as $extra) {
            if (! empty($extra['path'])) {
                $orderedInputs[] = ['local_path' => $extra['path'], 'kind' => GenerationInput::KIND_DETAIL];
            }
        }

        $this->ingestion->captureGeneration(
            sourceGenerationId: $sourceGenerationId,
            mannequinExternalId: (string) $mannequin->id,
            poseExternalId: (string) $pose->id,
            productExternalId: (string) $product->id,
            productSku: $product->sku ?? null,
            productName: $product->name,
            productCategory: $product->category?->name,
            renderedPrompt: $event->renderedPrompt,
            canonicalPromptTemplate: (new GeminiTryOnPromptBuilder())->build([], null, null),
            modelProvider: 'google',
            modelName: $event->modelName,
            modelParams: $event->imageConfig !== [] ? $event->imageConfig : null,
            latencyMs: $event->latencyMs,
            requestedAt: $context['requested_at'],
            orderedInputs: $orderedInputs,
        );
    }
}
