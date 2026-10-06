<?php

namespace Modules\Creative\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Mockup\MockupPipelineService;
use Modules\Creative\Services\Mockup\MockupSettings;

/**
 * Mokap kapsamını doldurur: eksik beden grubu olan ürünlerin gruplarını günlük kalan hak kadar başlatır
 * ("mokaplar her zaman hazır" — sitede her beden kendi yaşındaki mankenle). Yalnız otomatik tetik açıkken.
 * Tetikler: yeni manken onayı (14–17 mankeni gelince 14–15 bedenler ona taşınır) ve günlük zamanlayıcı
 * (limit dolduğu için kalan ürünler ertesi gün devam eder).
 */
class FillMockupCoverageJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function handle(MockupSettings $settings, MockupPipelineService $pipeline): void
    {
        if (! $settings->autoTrigger() || ($remaining = $settings->remainingToday()) <= 0) {
            return;
        }

        $result = $pipeline->startBulk($remaining);
        if ($result['started'] > 0) {
            Log::info('Mokap kapsamı dolduruluyor', $result);
        }
    }
}
