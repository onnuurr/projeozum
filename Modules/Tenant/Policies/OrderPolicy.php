<?php

namespace Modules\Tenant\Policies;

use App\Models\User;
use Modules\Product\Models\Order;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $order->tenant_id !== null
            && (int) $user->tenant_id === (int) $order->tenant_id;
    }
}
