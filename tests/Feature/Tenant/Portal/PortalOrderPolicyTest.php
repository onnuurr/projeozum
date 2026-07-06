<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['portal.access', 'portal.orders.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.orders.view']);
    }

    public function test_tenant_user_can_view_own_order(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'own-' . uniqid()]);
        $order = Order::factory()->forTenant($tenant)->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');

        $this->actingAs($user)
            ->get("http://{$tenant->slug}.bizimsite.test/orders/{$order->id}")
            ->assertOk();
    }

    public function test_cross_tenant_order_view_returns_403(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'oa-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'ob-' . uniqid()]);
        $orderB = Order::factory()->forTenant($tenantB)->create();

        $userOfA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userOfA->assignRole('tenant');

        $this->actingAs($userOfA)
            ->get("http://{$tenantA->slug}.bizimsite.test/orders/{$orderB->id}")
            ->assertForbidden();
    }
}
