<?php

namespace Tests\Fixtures\TenantIsolation;

class ProtectedController
{
    public function index($tenant)
    {
        // Zincirli accessibleToTenant() çağrısı — bare pattern'e hiç uymaz.
        return Product::query()->accessibleToTenant($tenant->id)->get();
    }

    public function guardedBulkExport($user, $order)
    {
        // Bare Order::all(), ama aynı metotta bir tenant_id karşılaştırması var
        // (Policy desenine benzer bir manuel guard) — korumalı sayılır.
        if ((int) $user->tenant_id !== (int) $order->tenant_id) {
            abort(403);
        }

        return Order::all();
    }
}
