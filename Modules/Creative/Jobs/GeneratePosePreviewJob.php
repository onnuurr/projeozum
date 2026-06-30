<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\Pose;
use Modules\Creative\Services\PoseService;
use Throwable;

/**
 * Bağımsız bir pozun nötr figür önizlemesini AI ile üretir (async, granüler retry).
 */
class GeneratePosePreviewJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $poseId) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(PoseService $service): void
    {
        $pose = Pose::find($this->poseId);

        if (! $pose) {
            return;
        }

        $pose->update(['status' => Pose::STATUS_GENERATING, 'error' => null]);

        try {
            $service->generate($pose);
        } catch (Throwable $e) {
            if ($this->attempts() < $this->tries) {
                $pose->update([
                    'status' => Pose::STATUS_GENERATING,
                    'error'  => sprintf('Deneme %d/%d başarısız: %s', $this->attempts(), $this->tries, $e->getMessage()),
                ]);

                throw $e;
            }

            $this->markFailed($pose, $e);
        }
    }

    public function failed(Throwable $e): void
    {
        $pose = Pose::find($this->poseId);
        if ($pose && $pose->status !== Pose::STATUS_FAILED) {
            $this->markFailed($pose, $e);
        }
    }

    private function markFailed(Pose $pose, Throwable $e): void
    {
        Log::warning('Poz önizleme üretimi başarısız', [
            'pose_id'  => $pose->id,
            'attempts' => $this->attempts(),
            'error'    => $e->getMessage(),
        ]);

        $pose->update([
            'status' => Pose::STATUS_FAILED,
            'error'  => $e->getMessage(),
        ]);
    }
}
