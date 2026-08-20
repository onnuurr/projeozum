<?php

namespace Modules\TrainingIngest\Console\Commands;

use App\Support\Media;
use Illuminate\Console\Command;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder;
use Modules\Creative\Services\CanvasAssetResolver;
use Modules\TrainingIngest\Exceptions\IngestionValidationException;
use Modules\TrainingIngest\Models\GenerationInput;
use Modules\TrainingIngest\Models\RejectionReason;
use Modules\TrainingIngest\Services\IngestionService;
use Throwable;

/**
 * Yedek/telafi yolu — birincil ve TAM veri kaybı olmayan yol event listener'lardır
 * (bkz. CaptureGenerationListener/CaptureReviewListener). Bu komut yalnızca bir
 * satırın O ANKİ (en güncel) durumunu görebilir: creative_tryon_results yerinde
 * güncellendiği için ret→yeniden-üretim ile ARADA kalmış denemeler burada YOKTUR —
 * event yolunun kaçırdığı (ör. queue kesintisi sırasında) satırları telafi etmek
 * ve ilk kurulumda geçmiş onaylı/reddedilmiş satırları bir kerelik aktarmak içindir.
 *
 * rendered_prompt SINIRLAMASI: orijinal, o anda gönderilen prompt metni HİÇBİR
 * yerde kalıcı değildi (bkz. Modules/Creative/Services/Ai/Support/TryonGenerationRendered
 * docblock'u) — bu yüzden burada GeminiTryOnPromptBuilder ile YENİDEN İNŞA edilir
 * (mevcut meta.extra_instructions ile). Bu, üretim anındaki protectListSentence /
 * otomatik tespit edilen ek referansları YENİDEN ÜRETMEZ (o an geçen garment scan
 * durumuna bağlıydı) — yani bu satırların rendered_prompt'u YAKLAŞIKTIR, orijinaliyle
 * birebir aynı olduğu garanti edilmez. Mannequin girdisi de aynı şekilde mankenin HAM
 * referans fotoğrafıdır, o üretimde kullanılan poz-compose edilmiş ara görsel değil
 * (o geçicidir, hiçbir yerde saklanmaz).
 */
class TrainingIngestCommand extends Command
{
    protected $signature = 'training:ingest
        {--from= : Başlangıç tarihi (YYYY-MM-DD), reviewed_at bazlı}
        {--to= : Bitiş tarihi (YYYY-MM-DD)}
        {--dry-run : Hiçbir şey yazmaz, ne olacağını raporlar}
        {--limit= : İşlenecek maksimum satır sayısı}';

    protected $description = 'Yedek/telafi: onaylanmış/reddedilmiş creative_tryon_results satırlarını training şemasına aktarır (idempotent).';

    public function handle(IngestionService $ingestion, CanvasAssetResolver $resolver): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        if (! $from || ! $to) {
            $this->error('--from ve --to zorunludur (YYYY-MM-DD).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = TryonResult::on('saas_readonly')
            ->whereIn('review_status', [TryonResult::REVIEW_APPROVED, TryonResult::REVIEW_REJECTED])
            ->whereBetween('reviewed_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
            ->with(['product.images', 'mannequin', 'pose', 'reviewer']);

        if ($limit) {
            $query->limit($limit);
        }

        $counts = ['read' => 0, 'written' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($query->cursor() as $result) {
            $counts['read']++;

            if ($dryRun) {
                $this->line("[dry-run] {$result->generation_token} — {$result->review_status}");

                continue;
            }

            try {
                $this->ingestOne($result, $ingestion, $resolver);
                $counts['written']++;
            } catch (IngestionValidationException $e) {
                $counts['failed']++;
                $this->warn("FAILED {$result->generation_token}: {$e->getMessage()}");
            } catch (Throwable $e) {
                $counts['failed']++;
                $this->warn("FAILED {$result->generation_token}: {$e->getMessage()}");
            }
        }

        $this->line(json_encode($counts, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    private function ingestOne(TryonResult $result, IngestionService $ingestion, CanvasAssetResolver $resolver): void
    {
        if (! $result->generation_token || ! $result->product || ! $result->mannequin || ! $result->pose) {
            throw new IngestionValidationException("Eksik ilişki (product/mannequin/pose): #{$result->id}");
        }

        $mannequinRef = Media::localPath($result->mannequin->reference_image_path);
        if (! $mannequinRef) {
            throw new IngestionValidationException("Manken referans görseli bulunamadı: #{$result->id}");
        }

        $garmentSrc = $result->garment_image_path ?: ($result->product->images->firstWhere('is_cover', true)?->path ?? $result->product->images->first()?->path);
        $garmentPath = $garmentSrc ? $resolver->toLocalPath($garmentSrc) : null;
        if (! $garmentPath) {
            throw new IngestionValidationException("Ürün görseli çözülemedi: #{$result->id}");
        }

        $orderedInputs = [
            ['local_path' => $mannequinRef, 'kind' => GenerationInput::KIND_MANNEQUIN],
            ['local_path' => $garmentPath, 'kind' => GenerationInput::KIND_FRONT],
        ];
        foreach ((array) ($result->meta['garment_extras'] ?? []) as $extra) {
            $extraPath = ! empty($extra['path']) ? $resolver->toLocalPath($extra['path']) : null;
            if ($extraPath) {
                $orderedInputs[] = ['local_path' => $extraPath, 'kind' => GenerationInput::KIND_DETAIL];
            }
        }

        $extraInstruction = trim((string) ($result->meta['extra_instructions'] ?? ''));
        $renderedPrompt = (new GeminiTryOnPromptBuilder())->build([], $extraInstruction !== '' ? $extraInstruction : null, null);

        $ingestion->captureGeneration(
            sourceGenerationId: $result->generation_token,
            mannequinExternalId: (string) $result->mannequin_id,
            poseExternalId: (string) $result->pose_id,
            productExternalId: (string) $result->product_id,
            productSku: $result->product->sku,
            productName: $result->product->name,
            productCategory: $result->product->category?->name,
            renderedPrompt: $renderedPrompt,
            canonicalPromptTemplate: (new GeminiTryOnPromptBuilder())->build([], null, null),
            modelProvider: 'google',
            modelName: $result->tryon_model ?: 'unknown',
            modelParams: null,
            latencyMs: $result->generation_duration_ms,
            requestedAt: $result->updated_at ?? $result->created_at,
            orderedInputs: $orderedInputs,
        );

        $outputPath = $result->staged_image_path ? Media::localPath($result->staged_image_path) : null;
        if (! $outputPath) {
            throw new IngestionValidationException("Çıktı görseli bulunamadı: #{$result->id}");
        }

        $tags = (array) ($result->review_tags ?? []);
        $reasonCode = null;
        if ($result->review_status === TryonResult::REVIEW_REJECTED) {
            $reasonCode = $tags !== []
                ? RejectionReason::query()->where('label', $tags[0])->value('code')
                : null;
            $reasonCode ??= 'OTHER';
        }

        $ingestion->captureReview(
            sourceGenerationId: $result->generation_token,
            outputLocalPath: $outputPath,
            verdict: $result->review_status,
            reasonCode: $reasonCode,
            reasonText: $tags !== [] ? (implode(', ', $tags) . ($result->review_note ? " — {$result->review_note}" : '')) : $result->review_note,
            severity: null,
            reviewer: $result->reviewer?->name ?? (string) $result->reviewed_by,
            reviewedAt: $result->reviewed_at ?? now(),
        );
    }
}
