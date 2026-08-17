<?php

namespace Modules\Tenant\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tenant\Models\Tenant;

/**
 * Tenant pasif hale getirildiğinde fırlatılır. Bagisto tarafındaki karşılık gelen
 * müşteri hesabını askıya alır (bkz. Modules\Bagisto\Listeners\PushTenantSync).
 */
class TenantDeactivated
{
    use Dispatchable;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}
}
