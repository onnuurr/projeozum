<?php

namespace Modules\PatternEngine\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PatternEngine\Models\PatternGenerationJob;
use Modules\PatternEngine\Services\PatternEngineService;
use Throwable;

/**
 * Tek bir kalıp üretim talebini (garment × size × profile) işler.
 *
 * `generation_token`, dispatch anındaki taşınan tekil değerdir — üretim
 * biterken bu satır başka bir `queue()` çağrısıyla geçersiz kılınmışsa (kullanıcı
 * arada parametre değiştirip yeniden üretime almışsa) eski işin çıktısının
 * satırı ezmesini engeller (bkz. Creative'in `GenerateOnModelJob`'ı, aynı desen).
 */
class GeneratePatternJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $jobId, public ?string $generationToken = null) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [5, 15];
    }

    public function handle(PatternEngineService $service): void
    {
        $job = PatternGenerationJob::find($this->jobId);

        if (! $job || ! $this->isCurrent($job)) {
            return;
        }

        $job->update(['status' => PatternGenerationJob::STATUS_PROCESSING, 'error' => null]);

        try {
            $service->generate($job);
        } catch (Throwable $e) {
            if (! $this->isCurrent($job->fresh())) {
                return;
            }

            if ($this->attempts() < $this->tries) {
                $job->update([
                    'status' => PatternGenerationJob::STATUS_PROCESSING,
                    'error'  => __('patternengine::messages.job_attempt_failed', ['attempt' => $this->attempts(), 'tries' => $this->tries, 'error' => $e->getMessage()]),
                ]);

                throw $e;
            }

            $this->markFailed($job, $e);
        }
    }

    public function failed(Throwable $e): void
    {
        $job = PatternGenerationJob::find($this->jobId);
        if ($job && $job->status !== PatternGenerationJob::STATUS_FAILED && $this->isCurrent($job)) {
            $this->markFailed($job, $e);
        }
    }

    private function isCurrent(?PatternGenerationJob $job): bool
    {
        if (! $job || $this->generationToken === null) {
            return true;
        }

        return $job->generation_token === $this->generationToken;
    }

    private function markFailed(PatternGenerationJob $job, Throwable $e): void
    {
        Log::warning('Kalıp üretimi başarısız', [
            'job_id'   => $job->id,
            'attempts' => $this->attempts(),
            'error'    => $e->getMessage(),
        ]);

        $job->update([
            'status' => PatternGenerationJob::STATUS_FAILED,
            'error'  => $e->getMessage(),
        ]);
    }
}
