<?php

namespace Modules\Marketplace\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class MarketplaceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Marketplace';

    protected string $nameLower = 'marketplace';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // Inertia testing view-finder'a "Marketplace::" namespace hint'i tanıt ki
        // assertInertia(component('Marketplace::Portal/Marketplace/Index')) çağrısı
        // modül sayfalarını dosya sisteminde bulabilsin.
        $this->app->resolving('inertia.testing.view-finder', function ($finder) {
            $finder->addNamespace('Marketplace', module_path('Marketplace', 'Resources/assets/js/Pages'));
        });
    }
}
