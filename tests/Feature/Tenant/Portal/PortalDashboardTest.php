<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['portal.access', 'portal.orders.view', 'portal.invoices.view', 'portal.credit.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.orders.view', 'portal.invoices.view', 'portal.credit.view']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web'])
            ->givePermissionTo(['portal.access', 'portal.orders.view', 'portal.invoices.view', 'portal.credit.view']);
    }

    private function loginTenantUser(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole('tenant');
        $this->actingAs($user);

        return $user;
    }

    public function test_dashboard_renders_with_snapshot(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 't-' . uniqid(), 'credit_limit' => 1000]);
        $this->loginTenantUser($tenant);

        $this->get('http://' . $tenant->slug . '.bizimsite.test/')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Tenant::Portal/Dashboard')
                ->where('snapshot.credit_limit', fn ($v) => (float) $v === 1000.0)
                ->has('topProducts')
                ->has('monthlyTrend')
            );
    }

    public function test_dashboard_does_not_leak_other_tenant_orders(): void
    {
        $tenantA = Tenant::factory()->create(['slug' => 'a-' . uniqid()]);
        $tenantB = Tenant::factory()->create(['slug' => 'b-' . uniqid()]);

        $orderB = Order::factory()->forTenant($tenantB)->create();
        OrderItem::factory()->create(['order_id' => $orderB->id, 'product_name' => 'Bayrak-B']);

        $this->loginTenantUser($tenantA);
        $this->get('http://' . $tenantA->slug . '.bizimsite.test/')
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('topProducts', [])
            );
    }
}
