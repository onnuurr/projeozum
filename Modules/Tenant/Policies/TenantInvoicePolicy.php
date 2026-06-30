<?php

namespace Modules\Tenant\Policies;

use App\Models\User;
use Modules\Tenant\Models\TenantInvoice;

class TenantInvoicePolicy
{
    public function view(User $user, TenantInvoice $invoice): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return (int) $user->tenant_id === (int) $invoice->tenant_id;
    }
}
