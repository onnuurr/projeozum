<?php

namespace Modules\TrainingIngest\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Creative\Events\TryonReviewed;
use Modules\Creative\Services\Ai\Support\TryonGenerationRendered;
use Modules\TrainingIngest\Listeners\CaptureGenerationListener;
use Modules\TrainingIngest\Listeners\CaptureReviewListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Modules\Creative'in event'lerine buradan abone olunur — TrainingIngest
     * Creative'e bağımlı, tersi yönde bir bağımlılık yoktur (module.json).
     * Otomatik keşfe (shouldDiscoverEvents) güvenmek yerine bilerek açıkça
     * listelenir — proje convention'ı (bkz. Modules/Finance/Providers/EventServiceProvider.php).
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TryonGenerationRendered::class => [CaptureGenerationListener::class],
        TryonReviewed::class           => [CaptureReviewListener::class],
    ];

    /**
     * Kapalı: kayıt bilerek yukarıda açıkça yapılıyor — otomatik keşif AÇIK olsaydı
     * aynı listener hem keşifle hem $listen'dan iki kez bağlanıp event'i iki kez
     * işleyebilirdi (idempotanlık bunu zararsız kılar ama gereksiz iş/log üretir).
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
