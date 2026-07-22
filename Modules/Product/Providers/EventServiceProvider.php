<?php

namespace Modules\Product\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Product\Events\AiAnalysisCompleted;
use Modules\Product\Events\MaterialChanged;
use Modules\Product\Events\MediaUploaded;
use Modules\Product\Events\ProductArchived;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductPublished;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Events\ProductVariantsSynced;
use Modules\Product\Listeners\RecordProductTimelineEntry;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ProductCreated::class        => [RecordProductTimelineEntry::class],
        ProductUpdated::class        => [RecordProductTimelineEntry::class],
        ProductVariantsSynced::class => [RecordProductTimelineEntry::class],
        MediaUploaded::class         => [RecordProductTimelineEntry::class],
        MaterialChanged::class       => [RecordProductTimelineEntry::class],
        AiAnalysisCompleted::class   => [RecordProductTimelineEntry::class],
        ProductPublished::class      => [RecordProductTimelineEntry::class],
        ProductArchived::class       => [RecordProductTimelineEntry::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
