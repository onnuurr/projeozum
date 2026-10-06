<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Services\Ai\Support\AiUsageRecorder;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Throwable;

/**
 * Bir ürünün mokap kombini + baz görsellerini üretir, sonra her poz için giydirme işini
 * (GenerateOnModelJob, pipeline=mockup) kuyruğa alır. Tek deneme: başarısızlıkta kombin
 * satırı failed + hata mesajıyla kalır (AI çağrıları pahalı; otomatik tekrar Faz 2'de).
 * Batch modunda iş birkaç kez çalışır (her batch sonucu geldiğinde AiBatchService yeniden
 * kuyruğa alır); kombin başına kilit iki kopyanın aynı anda ilerlemesini engeller.
 */
class GenerateMockupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    // Stilist + ana kare + 2 türetilmiş poz; her baz Gemini (~20-30 sn) + segmentasyon (~15-20 sn, CPU).
    public int $timeout = 1200;

    public function __construct(public int $outfitId) {}

    public function handle(MockupPipelineService $pipeline): void
    {
        $outfit = MockupOutfit::find($this->outfitId);
        if (! $outfit || ! in_array($outfit->status, [MockupOutfit::STATUS_QUEUED, MockupOutfit::STATUS_GENERATING], true)) {
            return;   // bitti — fazladan kopyada AI çağrısı yapılmaz
        }

        $lock = Cache::lock("creative:mockup-outfit:{$this->outfitId}", $this->timeout);
        if (! $lock->get()) {
            // Başka bir kopya şu an ilerliyor (ör. batch sonucu tam o sırada geldi): sonra yeniden bak.
            self::dispatch($this->outfitId)->delay(now()->addSeconds(60));

            return;
        }

        try {
            app(AiUsageRecorder::class)->forSubject($outfit, fn () => $pipeline->run($outfit));
        } catch (Throwable $e) {
            Log::warning('Mokap kombini/baz üretimi başarısız', ['outfit_id' => $this->outfitId, 'error' => $e->getMessage()]);
        } finally {
            $lock->release();
        }
    }
}
