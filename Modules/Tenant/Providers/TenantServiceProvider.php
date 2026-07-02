<?php

namespace Modules\Tenant\Providers;

use Illuminate\Support\Facades\Gate;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Policies\OrderPolicy;
use Modules\Tenant\Policies\TenantInvoicePolicy;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TenantServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Tenant';

    protected string $nameLower = 'tenant';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(TenantInvoice::class, TenantInvoicePolicy::class);

        // Portal alt kullanıcı yönetimi: hedef aynı tenant'a ait bir tenant-user olmalı.
        // (Aktör admin=tenant rolü; portal.users.manage rota middleware'inde kontrol edilir.
        //  Bu ability yalnız hedef-scope + kendini/başka admini yönetememe kuralını uygular.
        //  Superadmin Gate::before ile geçer.)
        Gate::define('portal-user.manage', function (\App\Models\User $actor, \App\Models\User $target): bool {
            return (int) $actor->tenant_id === (int) $target->tenant_id
                && $target->hasRole('tenant-user');
        });

        // Inertia testing view-finder'a "Tenant::" namespace hint'i tanıt ki
        // assertInertia(component('Tenant::Portal/Dashboard')) çağrısı modül
        // sayfalarını dosya sisteminde bulabilsin.
        $this->app->resolving('inertia.testing.view-finder', function ($finder) {
            $finder->addNamespace('Tenant', module_path('Tenant', 'Resources/assets/js/Pages'));
        });
    }
}
