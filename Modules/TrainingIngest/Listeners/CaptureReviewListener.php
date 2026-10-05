<?php

namespace Modules\TrainingIngest\Listeners;

use App\Models\User;
use App\Support\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Creative\Events\TryonReviewed;
use Modules\Creative\Models\TryonResult;
use Modules\TrainingIngest\Exceptions\IngestionValidationException;
use Modules\TrainingIngest\Models\RejectionReason;
use Modules\TrainingIngest\Services\IngestionService;
use Throwable;

/**
 * Bir giydirme kararının (onay/ret) kalıcı yakalama noktası — bkz. TryonReviewed.
 * İlişkili generation (bkz. CaptureGenerationListener) henüz işlenmemişse
 * IngestionService bir (retry edilebilir) RuntimeException fırlatır; bu listener
 * onu YUTMAZ, kuyruğun standart tries/backoff'una bırakır.
 */
class CaptureReviewListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    public function __construct(private readonly IngestionService $ingestion) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [15, 30, 60, 120];
    }

    public function handle(TryonReviewed $event): void
    {
        if (! config('training-ingest.enabled')) {
            // training_writer/training_reader rolleri ve R2 bucket'ı kurulana kadar
            // kapalı — bkz. Modules/TrainingIngest/config/config.php.
            return;
        }

        if (! is_string($event->generationToken) || $event->generationToken === '') {
            $this->fail(new IngestionValidationException(
                "TryonReviewed: generation_token boş, sonuç yakalanamıyor (result #{$event->resultId})."
            ));

            return;
        }

        $result = TryonResult::find($event->resultId);

        // Yakın plan / detay çekimleri (shot != 'full') giydirme üretimi değil — eğitim
        // verisine (try-on yakalaması) girmez; TryonGenerationRendered de onlar için yayılmaz.
        if ($result?->isCloseup()) {
            return;
        }
        $localPath = $result?->staged_image_path ? Media::localPath($result->staged_image_path) : null;

        if (! $localPath) {
            $this->fail(new IngestionValidationException(
                "TryonReviewed: çıktı görseli diskte bulunamadı (result #{$event->resultId})."
            ));

            return;
        }

        $reasonCode = null;
        $reasonText = $event->reviewNote;

        if ($event->verdict === 'rejected') {
            $tags = $event->reviewTags ?? [];
            $firstTag = $tags[0] ?? null;
            $reasonCode = $firstTag !== null
                ? RejectionReason::query()->where('label', $firstTag)->value('code')
                : null;
            $reasonCode ??= 'OTHER';

            // Birden fazla etiket seçilmiş olabilir (training.reviews.reason_code TEKİL);
            // kalan bağlamı kaybetmemek için hepsi reason_text'e katlanır.
            if ($tags !== []) {
                $reasonText = trim(implode(', ', $tags) . ($reasonText ? " — {$reasonText}" : ''));
            }
        }

        $reviewer = User::find($event->reviewerId)?->name ?? (string) ($event->reviewerId ?? 'bilinmiyor');

        try {
            $this->ingestion->captureReview(
                sourceGenerationId: $event->generationToken,
                outputLocalPath: $localPath,
                verdict: $event->verdict,
                reasonCode: $reasonCode,
                reasonText: $reasonText,
                severity: null,
                reviewer: $reviewer,
                reviewedAt: $event->reviewedAt ?? now(),
            );
        } catch (IngestionValidationException $e) {
            $this->fail($e);
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
