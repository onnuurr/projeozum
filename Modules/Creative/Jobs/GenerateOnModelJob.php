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

    // Dispatch anındaki generation_token — üretim biterken bu satır başka bir queue()
    // çağrısıyla geçersiz kılınmışsa (kullanıcı arada yeni manken/ürün seçip yeniden
    // üretime almışsa) eski işin çıktısının satırı ezmesini engeller. Bkz.
    // ProductOnModelService::queue/generate.
    public function __construct(public int $resultId, public ?string $generationToken = null) {}

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

        if (! $this->isCurrent($result)) {
            return;
        }

        $result->update(['status' => TryonResult::STATUS_GENERATING, 'error' => null]);

        try {
            $service->generate($result, $this->generationToken);
        } catch (Throwable $e) {
            if (! $this->isCurrent($result->fresh())) {
                return;
            }

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
        if ($result && $result->status !== TryonResult::STATUS_FAILED && $this->isCurrent($result)) {
            $this->markFailed($result, $e);
        }
    }

    /**
     * Bu işin taşıdığı token hâlâ satırın güncel token'ıyla eşleşiyor mu? Eşleşmiyorsa
     * (veya token hiç taşınmıyorsa — eski/manuel dispatch) satır başka bir üretimle
     * geçersiz kılınmamış demektir ya da kontrol devre dışıdır; true döner.
     */
    private function isCurrent(?TryonResult $result): bool
    {
        if (! $result || $this->generationToken === null) {
            return true;
        }

        return $result->generation_token === $this->generationToken;
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
