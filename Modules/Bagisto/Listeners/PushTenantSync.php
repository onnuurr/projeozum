<?php

namespace Modules\Bagisto\Listeners;

use Modules\Bagisto\Jobs\DeleteTenantFromBagisto;
use Modules\Bagisto\Jobs\PushTenantToBagisto;
use Modules\Bagisto\Jobs\RenameTenantInBagisto;
use Modules\Tenant\Events\TenantActivated;
use Modules\Tenant\Events\TenantDeactivated;
use Modules\Tenant\Events\TenantDeleted;
use Modules\Tenant\Events\TenantOwnerDetached;
use Modules\Tenant\Events\TenantOwnerEmailChanged;

class PushTenantSync
{
    public function handle(TenantActivated|TenantDeactivated|TenantDeleted|TenantOwnerDetached|TenantOwnerEmailChanged $event): void
    {
        if ($event instanceof TenantDeleted) {
            if ($event->ownerEmail) {
                DeleteTenantFromBagisto::dispatch($event->ownerEmail);
            }

            return;
        }

        if ($event instanceof TenantOwnerDetached) {
            DeleteTenantFromBagisto::dispatch($event->email);

            return;
        }

        if ($event instanceof TenantOwnerEmailChanged) {
            RenameTenantInBagisto::dispatch($event->oldEmail, $event->newEmail);

            return;
        }

        PushTenantToBagisto::dispatch(
            $event->tenant->id,
            $event instanceof TenantActivated ? 'activated' : 'deactivated',
            $event instanceof TenantActivated ? $event->plainPassword : null,
        );
    }
}
