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
    }
}
