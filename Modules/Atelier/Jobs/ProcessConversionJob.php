<?php

namespace Modules\Atelier\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Atelier\Models\ConversionJob;
use Modules\Atelier\Services\Conversion\ConversionPipelineService;

/**
 * Tek bir PDF'i arka planda DXF'e çevirir (PyMuPDF/ezdxf servisi yavaş olabilir).
 */
class ProcessConversionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Geçici hatalar (servis/ağ timeout) için yeniden dene.
    public int $tries = 3;

    // PyMuPDF + ezdxf dönüşümü büyük dosyalarda uzun sürebilir.
    public int $timeout = 300;

    public function __construct(public int $conversionJobId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(ConversionPipelineService $pipeline): void
    {
        $job = ConversionJob::find($this->conversionJobId);
        if (! $job) {
            return; // iş silinmişse sessizce çık
        }

        $pipeline->process($job);
    }
}
