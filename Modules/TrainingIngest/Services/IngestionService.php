<?php

namespace Modules\TrainingIngest\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\TrainingIngest\Exceptions\IngestionValidationException;
use Modules\TrainingIngest\Models\Asset;
use Modules\TrainingIngest\Models\Generation;
use Modules\TrainingIngest\Models\GenerationInput;
use Modules\TrainingIngest\Models\GenerationOutput;
use Modules\TrainingIngest\Models\IngestionLog;
use Modules\TrainingIngest\Models\Mannequin;
use Modules\TrainingIngest\Models\Pose;
use Modules\TrainingIngest\Models\Product;
use Modules\TrainingIngest\Models\PromptVersion;
use Modules\TrainingIngest\Models\Review;
use RuntimeException;
use Throwable;

/**
 * training.* şemasının TEK yazma noktası — hem event listener'lar (birincil,
 * gerçek zamanlı yol) hem de training:ingest backfill CLI'ı (ikincil, telafi
 * yolu) bunu çağırır; iki yol arasında kod tekrarı yoktur (bkz. plan bölüm 4).
 * Her iki yöntem de aynı doğrulama kurallarına ve aynı iki-transaction yazım
 * desenine (bkz. captureGeneration/captureReview) tabidir.
 */
class IngestionService
{
    public function __construct(private readonly TrainingStorageService $storage) {}

    /**
     * Bir üretim denemesini (girdi görselleri + nihai prompt) kalıcı hale getirir.
     * Henüz çıktı/karar YOK — bu {@see captureReview()} ile ayrı bir olayda gelir
     * (bkz. Modules/Creative: TryonGenerationRendered farklı bir andan, TryonReviewed
     * farklı bir andan gelir).
     *
     * @param  array<int,array{local_path:string,kind:string}>  $orderedInputs  Dizi index'i = position.
     * @param  array<string,mixed>|null  $modelParams
     */
    public function captureGeneration(
        string $sourceGenerationId,
        string $mannequinExternalId,
        string $poseExternalId,
        string $productExternalId,
        ?string $productSku,
        ?string $productName,
        ?string $productCategory,
        string $renderedPrompt,
        string $canonicalPromptTemplate,
        string $modelProvider,
        string $modelName,
        ?array $modelParams,
        ?int $latencyMs,
        \DateTimeInterface $requestedAt,
        array $orderedInputs,
    ): Generation {
        $existing = Generation::query()->where('source_generation_id', $sourceGenerationId)->first();
        if ($existing) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_SKIPPED, 'generation zaten var (idempotent)');

            return $existing;
        }

        // Kural 3: rendered_prompt boş değil.
        if (trim($renderedPrompt) === '') {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, 'rendered_prompt boş');

            throw new IngestionValidationException("rendered_prompt boş: {$sourceGenerationId}");
        }

        if ($orderedInputs === []) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, 'hiç girdi görseli yok');

            throw new IngestionValidationException("En az 1 girdi görseli gerekli: {$sourceGenerationId}");
        }

        $at = Carbon::instance($requestedAt);

        try {
            $generation = DB::connection('training')->transaction(function () use (
                $sourceGenerationId, $mannequinExternalId, $poseExternalId, $productExternalId,
                $productSku, $productName, $productCategory, $renderedPrompt, $canonicalPromptTemplate,
                $modelProvider, $modelName, $modelParams, $latencyMs, $requestedAt, $orderedInputs, $at,
            ) {
                // Kural 6: mannequin_id, pose_id, product_id üçü de çözümlenebiliyor.
                $mannequin = $this->resolveMannequin($mannequinExternalId);
                $pose = $this->resolvePose($poseExternalId);
                $product = $this->resolveProduct($productExternalId, $productSku, $productName, $productCategory);

                // Kural 4: prompt_version_id çözümlenebiliyor; şablon eşleşmiyorsa
                // hash bazlı yeni sürüm otomatik oluşturulur.
                $promptVersion = $this->resolvePromptVersion($canonicalPromptTemplate);

                $generation = Generation::create([
                    'source_generation_id' => $sourceGenerationId,
                    'mannequin_id'         => $mannequin->id,
                    'pose_id'              => $pose->id,
                    'product_id'           => $product->id,
                    'prompt_version_id'    => $promptVersion->id,
                    'rendered_prompt'      => $renderedPrompt,
                    'model_provider'       => $modelProvider,
                    'model_name'           => $modelName,
                    'model_params'         => $modelParams,
                    'latency_ms'           => $latencyMs,
                    'requested_at'         => $requestedAt,
                ]);

                // Kural 1: en az 1 adet product_input görseli var.
                $hasProductInput = false;

                foreach ($orderedInputs as $position => $input) {
                    // Kural 7: görsel hash'i hesaplanabildi, byte_size > 0 —
                    // TrainingStorageService::store() bunu zaten garanti eder (aksi
                    // halde RuntimeException fırlatır, transaction rollback olur).
                    $role = $this->roleForKind($input['kind']);
                    $hasProductInput = $hasProductInput || $role === Asset::ROLE_PRODUCT_INPUT;

                    $asset = $this->storage->store($input['local_path'], $sourceGenerationId, $role, $position, $at);

                    GenerationInput::create([
                        'generation_id' => $generation->id,
                        'asset_id'      => $asset->id,
                        'position'      => $position,
                        'input_kind'    => $input['kind'],
                    ]);
                }

                if (! $hasProductInput) {
                    throw new IngestionValidationException("En az 1 product_input rolünde görsel gerekli: {$sourceGenerationId}");
                }

                return $generation;
            });
        } catch (Throwable $e) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, $e->getMessage());

            throw $e;
        }

        $this->log($sourceGenerationId, IngestionLog::STATUS_SUCCESS, null, ['generation_id' => $generation->id]);

        return $generation;
    }

    /**
     * Bir insan kararını (onay/ret) çıktı görseliyle birlikte kalıcı hale getirir.
     * İlişkili generation henüz yoksa (queue sırası garantisiz — bkz. Modules/Creative
     * TryonGenerationRendered/TryonReviewed event'lerinin işlenme sırası) normal
     * (retry edilebilir) bir RuntimeException fırlatır — IngestionValidationException
     * DEĞİL, çünkü bu geçici bir durumdur, kalıcı bir veri hatası değil.
     */
    public function captureReview(
        string $sourceGenerationId,
        string $outputLocalPath,
        string $verdict,
        ?string $reasonCode,
        ?string $reasonText,
        ?int $severity,
        string $reviewer,
        \DateTimeInterface $reviewedAt,
    ): Review {
        $generation = Generation::query()->where('source_generation_id', $sourceGenerationId)->first();
        if (! $generation) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, 'ilişkili generation henüz yok (retry edilecek)');

            throw new RuntimeException("İlişkili generation henüz training şemasında yok: {$sourceGenerationId}");
        }

        // Kural 5: verdict geçerli bir değer; rejected ise reason_code dolu.
        // (reason_code -> rejection_reasons.code FK'sı da DB seviyesinde ayrıca zorlar.)
        if (! in_array($verdict, [Review::VERDICT_APPROVED, Review::VERDICT_REJECTED], true)) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, "geçersiz verdict: {$verdict}");

            throw new IngestionValidationException("Geçersiz verdict: {$verdict}");
        }
        if ($verdict === Review::VERDICT_REJECTED && ($reasonCode === null || $reasonCode === '')) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, 'rejected ama reason_code boş');

            throw new IngestionValidationException("rejected verdict için reason_code zorunlu: {$sourceGenerationId}");
        }

        $existingOutput = GenerationOutput::query()
            ->where('generation_id', $generation->id)
            ->where('variant_index', 0)
            ->first();

        if ($existingOutput && $existingOutput->reviews()->exists()) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_SKIPPED, 'zaten incelenmiş (idempotent)');

            return $existingOutput->reviews()->latest('created_at')->firstOrFail();
        }

        try {
            $review = DB::connection('training')->transaction(function () use (
                $sourceGenerationId, $outputLocalPath, $verdict, $reasonCode, $reasonText,
                $severity, $reviewer, $reviewedAt, $generation, $existingOutput,
            ) {
                // Kural 2: en az 1 adet generated_output görseli var ve R2'de erişilebilir
                // — store() yükleme sonrası boyut doğrulaması yapar (aksi halde throw eder).
                $asset = $this->storage->store($outputLocalPath, $sourceGenerationId, Asset::ROLE_GENERATED_OUTPUT, 0);

                $output = $existingOutput ?? GenerationOutput::create([
                    'generation_id' => $generation->id,
                    'asset_id'      => $asset->id,
                    'variant_index' => 0,
                ]);

                return Review::create([
                    'generation_output_id' => $output->id,
                    'verdict'              => $verdict,
                    'reason_code'          => $reasonCode,
                    'reason_text'          => $reasonText,
                    'severity'             => $severity,
                    'reviewer'             => $reviewer,
                    'reviewed_at'          => $reviewedAt,
                ]);
            });
        } catch (Throwable $e) {
            $this->log($sourceGenerationId, IngestionLog::STATUS_FAILED, $e->getMessage());

            throw $e;
        }

        $this->log($sourceGenerationId, IngestionLog::STATUS_SUCCESS, null, ['review_id' => $review->id]);

        return $review;
    }

    private function resolveMannequin(string $externalId, ?array $attributes = null, ?string $label = null): Mannequin
    {
        return Mannequin::query()->firstOrCreate(
            ['external_id' => $externalId],
            ['label' => $label, 'attributes' => $attributes],
        );
    }

    private function resolvePose(string $externalId, ?array $attributes = null, ?string $label = null): Pose
    {
        return Pose::query()->firstOrCreate(
            ['external_id' => $externalId],
            ['label' => $label, 'attributes' => $attributes],
        );
    }

    private function resolveProduct(string $externalId, ?string $sku, ?string $name, ?string $category): Product
    {
        return Product::query()->firstOrCreate(
            ['external_id' => $externalId],
            ['sku' => $sku, 'name' => $name, 'category' => $category],
        );
    }

    /**
     * Şablon metnini (dinamik slotları BOŞ, yani prompt builder'ın statik iskeleti —
     * bkz. çağıran taraf: GeminiTryOnPromptBuilder::build([], null, null)) hash'ler;
     * eşleşen sürüm yoksa yeni bir tane açar ve öncekini pasifleştirir. Bu, prompt
     * metnindeki gerçek bir mühendislik değişikliğini (dosya değişince hash değişir)
     * yakalar — her üretimdeki dinamik ek talimatları DEĞİL (aksi halde hemen her
     * üretim farklı bir "sürüm" sayılırdı, ret-oranı raporlaması anlamsızlaşırdı).
     */
    private function resolvePromptVersion(string $canonicalTemplate): PromptVersion
    {
        $hash = hash('sha256', $canonicalTemplate);

        $existing = PromptVersion::query()->where('template_sha256', $hash)->first();
        if ($existing) {
            return $existing;
        }

        PromptVersion::query()->where('is_active', true)->update([
            'is_active'      => false,
            'deactivated_at' => now(),
        ]);

        return PromptVersion::create([
            'version_label'   => $this->nextVersionLabel(),
            'template_text'   => $canonicalTemplate,
            'template_sha256' => $hash,
            'is_active'       => true,
            'activated_at'    => now(),
        ]);
    }

    private function nextVersionLabel(): string
    {
        $date = now()->format('Y.m.d');
        $countToday = PromptVersion::query()->whereDate('created_at', now())->count();
        $suffix = chr(ord('a') + $countToday);

        return "v{$date}-{$suffix}";
    }

    private function roleForKind(string $kind): string
    {
        return $kind === GenerationInput::KIND_MANNEQUIN
            ? Asset::ROLE_MANNEQUIN_INPUT
            : Asset::ROLE_PRODUCT_INPUT;
    }

    private function log(string $sourceGenerationId, string $status, ?string $errorDetail, ?array $payload = null): void
    {
        IngestionLog::create([
            'source_generation_id' => $sourceGenerationId,
            'status'                => $status,
            'error_detail'          => $errorDetail,
            'payload_snapshot'      => $payload,
        ]);
    }
}
