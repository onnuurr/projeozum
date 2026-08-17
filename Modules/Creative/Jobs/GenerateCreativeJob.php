<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Services\CreativeRenderService;
use Modules\Creative\Services\Exceptions\PermanentRenderException;
use Throwable;

class GenerateCreativeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Geçici hatalar (AI/render timeout, ağ) için yeniden dene; kalıcı hatalar
    // (geçersiz şablon/ürün) retry edilmez — PermanentRenderException ile ayrılır.
    public int $tries = 3;

    // AI sahne pipeline (Gemini compose + fal poll) uzun sürebilir.
    public int $timeout = 300;

    // Dispatch anındaki generation_token — üretim biterken bu satır başka bir
    // regenerate()/applyAsset() çağrısıyla geçersiz kılınmışsa (kullanıcı arada
    // yeni bir düzeltme talimatı uygulayıp yeniden üretime almışsa) eski işin
    // çıktısının satırı ezmesini engeller. Bkz. CreativeStudioController::regenerate,
    // ReviewChatController::applyAsset, CreativeRenderService::generate.
    public function __construct(public int $assetId, public ?string $generationToken = null) {}

    /**
     * Denemeler arası bekleme (saniye): 10s, 30s.
     *
     * @return array<int,int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(CreativeRenderService $service): void
    {
        $asset = CreativeAsset::find($this->assetId);

        if (! $asset || ! $this->isCurrent($asset)) {
            return;
        }

        $asset->update(['status' => CreativeAsset::STATUS_PROCESSING, 'error' => null]);

        try {
            $service->generate($asset, $this->generationToken);
        } catch (PermanentRenderException $e) {
            // Kalıcı hata: işaretle ve YUTMA değil — retry'ı engellemek için rethrow etme.
            $this->markFailed($asset, $e);

            return;
        } catch (Throwable $e) {
            if (! $this->isCurrent($asset->fresh())) {
                return;
            }

            // Geçici hata: son deneme değilse retry için rethrow et.
            if ($this->attempts() < $this->tries) {
                $asset->update([
                    'status' => CreativeAsset::STATUS_QUEUED,
                    'error'  => sprintf('Deneme %d/%d başarısız: %s', $this->attempts(), $this->tries, $e->getMessage()),
                ]);

                throw $e;
            }

            $this->markFailed($asset, $e);
        }
    }

    /**
     * Tüm denemeler tükendiğinde (rethrow sonrası) çağrılır.
     */
    public function failed(Throwable $e): void
    {
        $asset = CreativeAsset::find($this->assetId);
        if ($asset && $asset->status !== CreativeAsset::STATUS_FAILED && $this->isCurrent($asset)) {
            $this->markFailed($asset, $e);
        }
    }

    /**
     * Bu işin taşıdığı token hâlâ satırın güncel token'ıyla eşleşiyor mu? Eşleşmiyorsa
     * (veya token hiç taşınmıyorsa — eski/manuel dispatch) satır başka bir üretimle
     * geçersiz kılınmamış demektir ya da kontrol devre dışıdır; true döner.
     */
    private function isCurrent(?CreativeAsset $asset): bool
    {
        if (! $asset || $this->generationToken === null) {
            return true;
        }

        return $asset->generation_token === $this->generationToken;
    }

    private function markFailed(CreativeAsset $asset, Throwable $e): void
    {
        Log::warning('Creative render başarısız', [
            'asset_id' => $asset->id,
            'attempts' => $this->attempts(),
            'error'    => $e->getMessage(),
        ]);

        $asset->update([
            'status' => CreativeAsset::STATUS_FAILED,
            'error'  => $e->getMessage(),
        ]);
    }
}
