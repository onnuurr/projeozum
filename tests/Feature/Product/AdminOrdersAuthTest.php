<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Product\Models\Order;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminOrdersAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'order.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'order.manage', 'guard_name' => 'web']);
    }

    private function order(): Order
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 1_000_000, 'current_balance' => 0]);

        return Order::factory()->forTenant($tenant)->create(['status' => Order::STATUS_PENDING, 'total' => 100]);
    }

    public function test_index_forbidden_without_order_view(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/orders')->assertForbidden();
    }

    public function test_index_allowed_with_order_view(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('order.view');

        // Inertia isteği olarak yap: JSON page object döner, blade/@vite manifest
        // araması olmaz (yeni sayfa henüz build edilmediği için gereklidir).
        // Doğru asset version'ı gönder; aksi halde Inertia 409 (reload) döner.
        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $this->actingAs($user)
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
            ->get('/orders')
            ->assertOk();
    }

    public function test_update_status_forbidden_without_order_manage(): void
    {
        $order = $this->order();

        // order.view yeterli DEĞİL — durum geçişi order.manage ister.
        $user = User::factory()->create();
        $user->givePermissionTo('order.view');

        $this->actingAs($user)
            ->put("/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED])
            ->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_update_status_allowed_with_order_manage(): void
    {
        $order = $this->order();

        $user = User::factory()->create();
        $user->givePermissionTo('order.manage');

        $this->actingAs($user)
            ->put("/orders/{$order->id}/status", ['status' => Order::STATUS_CONFIRMED, 'note' => 'ok'])
            ->assertRedirect();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
    }
}
