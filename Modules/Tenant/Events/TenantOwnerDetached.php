<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\Tenant;

/**
 * Tenant'ın owner'ı (primaryUser — Bagisto hesabının sahibi) silindiğinde ya da
 * başka bir tenant'a/tenant'sız duruma taşındığında fırlatılır. O e-postanın Bagisto
 * müşteri hesabı kaldırılır (bkz. Modules\Bagisto\Listeners\PushTenantSync); aksi
 * halde silinen kişi mağazaya bayi fiyatıyla girmeye devam ediyordu.
 */
class TenantOwnerDetached
{
    use Dispatchable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $email,
    ) {}
}
