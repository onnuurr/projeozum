<?php

namespace Modules\Atelier\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Services\Concept\ConceptStudioService;

/**
 * AI konsept görsellerini arka planda üretir (Gemini çoklu görselde yavaş olabilir).
 * Kartın saklı request_params'ından tarif yeniden kurulur.
 */
class GenerateConceptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $designCardId) {}

    /** @return array<int,int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(ConceptStudioService $studio): void
    {
        $card = DesignCard::find($this->designCardId);
        if (! $card) {
            return; // kart silinmişse sessizce çık
        }

        $studio->runGeneration($card);
    }
}
