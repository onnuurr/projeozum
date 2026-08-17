<?php

namespace Modules\Bagisto\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Bagisto\Listeners\PushProductSync;
use Modules\Bagisto\Listeners\PushStockSync;
use Modules\Bagisto\Listeners\PushTenantSync;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Events\StockChanged;
use Modules\Tenant\Events\TenantActivated;
use Modules\Tenant\Events\TenantDeactivated;

/**
 * Product/Tenant modüllerinin domain event'lerine, o modülleri hiç değiştirmeden
 * dışarıdan bağlanır (event-driven extensibility, bkz. proje CLAUDE.md/mimari
 * kuralları). `ProductVariantsSynced` bilerek dinlenmiyor: create/update akışında
 * variant senkronu her zaman Created/Updated ile aynı transaction'da olur, ayrıca
 * dinlemek aynı ürün için çifte push'a yol açardı.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        ProductCreated::class    => [PushProductSync::class],
        ProductUpdated::class    => [PushProductSync::class],
        ProductDeleted::class    => [PushProductSync::class],
        StockChanged::class      => [PushStockSync::class],
        TenantActivated::class   => [PushTenantSync::class],
        TenantDeactivated::class => [PushTenantSync::class],
    ];

    protected static $shouldDiscoverEvents = false;
}
