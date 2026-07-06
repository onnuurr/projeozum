<?php

namespace Tests\Feature\Tenant\Foundations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class OrderTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_b2c_order_has_null_tenant_and_b2c_type(): void
    {
        $order = Order::create([
            'order_no'    => 'TEST-B2C-1',
            'user_id'     => \App\Models\User::factory()->create()->id,
            'tenant_id'   => null,
            'subtotal'    => 100,
            'total'       => 100,
            'status'      => 'pending',
            'order_type'  => Order::TYPE_B2C,
        ]);

        $this->assertNull($order->fresh()->tenant_id);
        $this->assertSame('b2c', $order->fresh()->order_type);
    }

    public function test_dropship_order_has_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);

        $order = Order::create([
            'order_no'    => 'TEST-DS-1',
            'user_id'     => $user->id,
            'tenant_id'   => $tenant->id,
            'subtotal'    => 250,
            'total'       => 250,
            'status'      => 'pending',
            'order_type'  => Order::TYPE_DROPSHIP,
        ]);

        $fresh = $order->fresh();
        $this->assertSame($tenant->id, $fresh->tenant_id);
        $this->assertSame('dropship', $fresh->order_type);
    }

    public function test_scope_dropship_filters_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        Order::create([
            'order_no' => 'B2C-A', 'user_id' => \App\Models\User::factory()->create()->id,
            'tenant_id' => null, 'subtotal' => 1, 'total' => 1, 'status' => 'pending', 'order_type' => Order::TYPE_B2C,
        ]);
        Order::create([
            'order_no' => 'DS-A', 'user_id' => \App\Models\User::factory()->create()->id,
            'tenant_id' => $tenant->id, 'subtotal' => 1, 'total' => 1, 'status' => 'pending', 'order_type' => Order::TYPE_DROPSHIP,
        ]);

        $this->assertSame(1, Order::query()->dropship()->count());
    }
}
