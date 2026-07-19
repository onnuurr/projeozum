<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\ProductOnModelService;
use Throwable;

/**
 * Tek bir giydirme sonucunu (ürün × poz) fashn/tryon ile üretir ve product_images'a
 * yazar (async, granüler retry).
 */
class GenerateOnModelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    // 300'den 700'e çıkarıldı: zero-shot giysi parça taraması (Faz G.3b, OWLv2)
    // ilk (hash-dedup'siz) taramada bu 2 vCPU'luk sunucuda ölçülen ~190-230sn CPU
    // çıkarım süresi alıyor (bkz. ROADMAP.md Faz G.3b); kalan bütçe Gemini try-on
    // çağrısı + enhance için gerekli.
    public int $timeout = 700;

    public function __construct(public int $resultId) {}

    /**
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(ProductOnModelService $service): void
    {
        $result = TryonResult::find($this->resultId);

        if (! $result) {
            return;
        }

        $result->update(['status' => TryonResult::STATUS_GENERATING, 'error' => null]);

        try {
            $service->generate($result);
        } catch (Throwable $e) {
            if ($this->attempts() < $this->tries) {
                $result->update([
                    'status' => TryonResult::STATUS_GENERATING,
                    'error'  => sprintf('Deneme %d/%d başarısız: %s', $this->attempts(), $this->tries, $e->getMessage()),
                ]);

                throw $e;
            }

            $this->markFailed($result, $e);
        }
    }

    public function failed(Throwable $e): void
    {
        $result = TryonResult::find($this->resultId);
        if ($result && $result->status !== TryonResult::STATUS_FAILED) {
            $this->markFailed($result, $e);
        }
    }

    private function markFailed(TryonResult $result, Throwable $e): void
    {
        Log::warning('Ürün giydirme başarısız', [
            'result_id' => $result->id,
            'attempts'  => $this->attempts(),
            'error'     => $e->getMessage(),
        ]);

        $result->update([
            'status' => TryonResult::STATUS_FAILED,
            'error'  => $e->getMessage(),
        ]);
    }
}
