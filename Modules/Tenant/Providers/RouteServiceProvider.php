<?php

namespace Modules\Tenant\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Tenant';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
        $this->mapPortalRoutes();
        $this->mapFeedRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }

    /**
     * Tenant portal subdomain routes: {slug}.{portal_domain}/...
     * Middleware: web stack + auth + email verified + subdomain tenant resolver + portal permission gate.
     */
    protected function mapPortalRoutes(): void
    {
        $domain = config('app.portal_domain');
        if (! $domain) {
            return;
        }

        Route::domain('{slug}.'.$domain)
            ->middleware(['web', 'auth', 'verified', 'tenant.subdomain', 'can:portal.access'])
            ->name('portal.')
            ->group(module_path($this->name, '/routes/portal.php'));
    }

    /**
     * XML feed: auth-siz, sadece token-gated. Subdomain group içinde ama
     * tenant.subdomain middleware'i bypass — controller içinde slug + token compare.
     */
    protected function mapFeedRoutes(): void
    {
        $domain = config('app.portal_domain');
        if (! $domain) {
            return;
        }

        Route::domain('{slug}.'.$domain)
            ->middleware(['web', 'throttle:60,1'])
            ->group(module_path($this->name, '/routes/feed.php'));
    }
}
