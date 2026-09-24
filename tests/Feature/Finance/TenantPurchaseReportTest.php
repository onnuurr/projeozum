<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Services\TenantPurchaseReportService;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantPurchaseReportTest extends TestCase
{
    use RefreshDatabase;

    private function bagistoOrder(Tenant $tenant, string $status, float $total): Order
    {
        return Order::factory()
            ->forTenant($tenant)
            ->create([
                'status'           => $status,
                'total'            => $total,
                'bagisto_order_id' => 'BG-' . fake()->unique()->numberBetween(1000, 9999),
            ]);
    }

    public function test_summary_counts_only_bagisto_orders(): void
    {
        $tenant = Tenant::factory()->create();

        $this->bagistoOrder($tenant, Order::STATUS_PENDING, 100);
        $this->bagistoOrder($tenant, Order::STATUS_DELIVERED, 250);

        // Bagisto dışı (bagisto_order_id null) — rapora dahil edilmemeli.
        Order::factory()->forTenant($tenant)->create(['status' => Order::STATUS_PENDING, 'total' => 999]);

        $summary = app(TenantPurchaseReportService::class)->summary();

        $this->assertSame(2, $summary['orderCount']);
        $this->assertSame(350.0, $summary['totalAmount']);
        $this->assertSame(1, $summary['statusCounts'][Order::STATUS_PENDING]);
        $this->assertSame(1, $summary['statusCounts'][Order::STATUS_DELIVERED]);
    }

    public function test_by_product_groups_and_sums_across_orders(): void
    {
        $tenant = Tenant::factory()->create();

        $orderA = $this->bagistoOrder($tenant, Order::STATUS_DELIVERED, 300);
        OrderItem::factory()->create([
            'order_id' => $orderA->id, 'product_name' => 'Kot Pantolon', 'qty' => 2, 'total_price' => 200,
        ]);

        $orderB = $this->bagistoOrder($tenant, Order::STATUS_PENDING, 100);
        OrderItem::factory()->create([
            'order_id' => $orderB->id, 'product_name' => 'Kot Pantolon', 'qty' => 1, 'total_price' => 100,
        ]);

        // Bagisto dışı sipariş — kırılıma girmemeli.
        $offOrder = Order::factory()->forTenant($tenant)->create();
        OrderItem::factory()->create(['order_id' => $offOrder->id, 'product_name' => 'Kot Pantolon', 'qty' => 99, 'total_price' => 9999]);

        $byProduct = app(TenantPurchaseReportService::class)->byProduct();

        $this->assertCount(1, $byProduct);
        $row = $byProduct->first();
        $this->assertSame('Kot Pantolon', $row['productName']);
        $this->assertSame(3, $row['qty']);
        $this->assertSame(300.0, $row['totalAmount']);
        $this->assertSame(2, $row['orderCount']);
    }

    public function test_list_includes_tenant_and_item_count(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Örnek Bayi']);
        $order  = $this->bagistoOrder($tenant, Order::STATUS_SHIPPED, 150);
        OrderItem::factory()->count(2)->create(['order_id' => $order->id]);

        $list = app(TenantPurchaseReportService::class)->list();

        $this->assertCount(1, $list);
        $row = $list->first();
        $this->assertSame('Örnek Bayi', $row['tenant']);
        $this->assertSame(2, $row['itemCount']);
        $this->assertSame(Order::STATUS_SHIPPED, $row['status']);
    }

    public function test_report_page_renders(): void
    {
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'finance.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'finance.manage', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo(['finance.view', 'finance.manage']);

        $this->actingAs($user)
            ->get('/finance/tenant-purchases')
            ->assertOk();
    }
}
