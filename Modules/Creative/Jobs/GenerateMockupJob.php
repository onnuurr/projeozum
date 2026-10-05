<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Services\Ai\Support\AiUsageRecorder;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Throwable;

/**
 * Bir ürünün mokap kombini + baz görsellerini üretir, sonra her poz için giydirme işini
 * (GenerateOnModelJob, pipeline=mockup) kuyruğa alır. Tek deneme: başarısızlıkta kombin
 * satırı failed + hata mesajıyla kalır (AI çağrıları pahalı; otomatik tekrar Faz 2'de).
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
        if (! $outfit || $outfit->status !== MockupOutfit::STATUS_QUEUED) {
            return;   // zaten işleniyor / bitti — çift çalıştırmada AI çağrısı yapılmaz
        }

        try {
            app(AiUsageRecorder::class)->forSubject($outfit, fn () => $pipeline->run($outfit));
        } catch (Throwable $e) {
            Log::warning('Mokap kombini/baz üretimi başarısız', ['outfit_id' => $this->outfitId, 'error' => $e->getMessage()]);
        }
    }
}
