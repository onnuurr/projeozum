<?php

namespace Modules\Bagisto\Providers;

use App\Events\SignupAvailabilityChanged;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Bagisto\Listeners\PushDealerApplicationsSwitch;
use Modules\Bagisto\Listeners\PushProductSync;
use Modules\Bagisto\Listeners\PushStockSync;
use Modules\Bagisto\Listeners\PushTenantApplicationStage;
use Modules\Bagisto\Listeners\PushTenantSync;
use Modules\Product\Events\ProductCreated;
use Modules\Product\Events\ProductDeleted;
use Modules\Product\Events\ProductImagesChanged;
use Modules\Product\Events\ProductStatusChanged;
use Modules\Product\Events\ProductUpdated;
use Modules\Product\Events\StockChanged;
use Modules\Tenant\Events\TenantActivated;
use Modules\Tenant\Events\TenantApplicationStageChanged;
use Modules\Tenant\Events\TenantDeactivated;
use Modules\Tenant\Events\TenantDeleted;
use Modules\Tenant\Events\TenantOwnerDetached;
use Modules\Tenant\Events\TenantOwnerEmailChanged;

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
        // Yayına alma/arşivleme/taslağa dönüş Bagisto'daki "status" alanını değiştirir;
        // bu olmadan arşivlenen ürün mağazada satışta kalıyordu (bkz. ProductStatusChanged).
        ProductStatusChanged::class => [PushProductSync::class],
        // Görsel ekleme/kapak/silme ProductService'i atlayan yollardan da (ProductImageController,
        // Creative "Kapak yap"/try-on onayı) gelir; bu olmadan mağaza görselleri eskide kalıyordu.
        ProductImagesChanged::class => [PushProductSync::class],
        StockChanged::class      => [PushStockSync::class],
        TenantActivated::class   => [PushTenantSync::class],
        TenantDeactivated::class => [PushTenantSync::class],
        TenantDeleted::class     => [PushTenantSync::class],
        TenantOwnerDetached::class => [PushTenantSync::class],
        TenantOwnerEmailChanged::class => [PushTenantSync::class],
        // Bayi portalı başvurusu: belge aşaması açıldı / eksik evrak / hesap açıldı / red → bayiye e-posta (mağaza gönderir).
        TenantApplicationStageChanged::class => [PushTenantApplicationStage::class],
        // Süper admin "Yeni kayıt açık" → bayi portalı başvuru formu açık/kapalı.
        SignupAvailabilityChanged::class => [PushDealerApplicationsSwitch::class],
    ];

    protected static $shouldDiscoverEvents = false;
}
