<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['order.view', 'tenant.view', 'product.view', 'atelier.view', 'atelier.pattern.view', 'creative.view'] as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
    }

    private function inertiaGet(User $user)
    {
        // Inertia isteği: JSON page object döner (build/manifest gerekmez); doğru version gönderilir.
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
            ->get('/dashboard');
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_sections_are_hidden_without_permissions(): void
    {
        $this->inertiaGet(User::factory()->create())
            ->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonPath('props.summary.orders', null)
            ->assertJsonPath('props.summary.tenants', null)
            ->assertJsonPath('props.summary.attention', []);
    }

    public function test_order_section_counts_open_orders_and_month_revenue(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);
        Order::factory()->forTenant($tenant)->count(2)->create(['status' => Order::STATUS_PENDING, 'total' => 100]);
        Order::factory()->forTenant($tenant)->create(['status' => Order::STATUS_SHIPPED, 'total' => 50]);
        Order::factory()->forTenant($tenant)->create(['status' => Order::STATUS_CANCELLED, 'total' => 999]);

        $user = User::factory()->create();
        $user->givePermissionTo('order.view');

        $res = $this->inertiaGet($user)->assertOk()
            ->assertJsonPath('props.summary.orders.open', 2)
            ->assertJsonPath('props.summary.orders.pending', 2)
            ->assertJsonPath('props.summary.orders.month_count', 4)
            ->assertJsonPath('props.summary.attention.0.key', 'orders_pending')
            ->assertJsonPath('props.summary.attention.0.count', 2)
            ->assertJsonPath('props.summary.tenants', null);

        // İptal edilen sipariş ciroya katılmaz; 30 günlük trend her günü içerir
        $this->assertEquals(250.0, $res->json('props.summary.orders.month_revenue'));
        $this->assertCount(30, $res->json('props.summary.orders.trend'));
        $this->assertSame(4, array_sum(array_column($res->json('props.summary.orders.trend'), 'orders')));
    }
}
