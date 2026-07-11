<?php

namespace Modules\Product\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Atelier\Models\ProductBom;
use Modules\Product\Observers\ProductBomObserver;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [];

    public function boot(): void
    {
        parent::boot();

        // Reçete kaydı → ürünün SEO içeriğini AI ile üret (kuyruk).
        ProductBom::observe(ProductBomObserver::class);
    }

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
