<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Jobs\PushTenantToBagisto;
use Modules\Tenant\Events\TenantActivated;
use Modules\Tenant\Events\TenantDeactivated;

class PushTenantSync
{
    public function handle(TenantActivated|TenantDeactivated $event): void
    {
        PushTenantToBagisto::dispatch(
            $event->tenant->id,
            $event instanceof TenantActivated ? 'activated' : 'deactivated',
            $event instanceof TenantActivated ? $event->plainPassword : null,
        );
    }
}
