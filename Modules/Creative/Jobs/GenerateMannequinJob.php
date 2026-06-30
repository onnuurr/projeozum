<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Services\MannequinService;
use Throwable;

/**
 * Bir mankenin kimlik referans görselini AI ile üretir (async).
 * Geçici hatalarda (AI/ağ timeout) yeniden dener.
 */
class GenerateMannequinJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $mannequinId) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(MannequinService $service): void
    {
        $mannequin = Mannequin::find($this->mannequinId);

        if (! $mannequin) {
            return;
        }

        $mannequin->update(['status' => Mannequin::STATUS_GENERATING, 'error' => null]);

        try {
            $service->generate($mannequin);
        } catch (Throwable $e) {
            // Geçici hata: son deneme değilse retry için rethrow et.
            if ($this->attempts() < $this->tries) {
                $mannequin->update([
                    'status' => Mannequin::STATUS_GENERATING,
                    'error'  => sprintf('Deneme %d/%d başarısız: %s', $this->attempts(), $this->tries, $e->getMessage()),
                ]);

                throw $e;
            }

            $this->markFailed($mannequin, $e);
        }
    }

    public function failed(Throwable $e): void
    {
        $mannequin = Mannequin::find($this->mannequinId);
        if ($mannequin && $mannequin->status !== Mannequin::STATUS_FAILED) {
            $this->markFailed($mannequin, $e);
        }
    }

    private function markFailed(Mannequin $mannequin, Throwable $e): void
    {
        Log::warning('Manken üretimi başarısız', [
            'mannequin_id' => $mannequin->id,
            'attempts'     => $this->attempts(),
            'error'        => $e->getMessage(),
        ]);

        $mannequin->update([
            'status' => Mannequin::STATUS_FAILED,
            'error'  => $e->getMessage(),
        ]);
    }
}
