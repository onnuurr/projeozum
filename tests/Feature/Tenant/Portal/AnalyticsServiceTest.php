<?php

namespace Tests\Feature\Tenant\Portal;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantCreditLedger;
use Modules\Tenant\Services\TenantPurchaseAnalyticsService;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantPurchaseAnalyticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TenantPurchaseAnalyticsService::class);
    }

    public function test_top_products_aggregates_by_qty_within_window(): void
    {
        $tenant = Tenant::factory()->create();
        $order = Order::factory()->forTenant($tenant)->create();

        // Ayrık product_id'ler olmadan GROUP BY tek gruba düşürür → 2 gerçek ürün.
        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pidA = DB::table('products')->insertGetId([
            'name' => 'A', 'slug' => 'a-' . uniqid(), 'sku' => 'SKU-A-' . uniqid(),
            'price' => 100, 'category_id' => $catId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pidB = DB::table('products')->insertGetId([
            'name' => 'B', 'slug' => 'b-' . uniqid(), 'sku' => 'SKU-B-' . uniqid(),
            'price' => 100, 'category_id' => $catId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $pidA, 'product_name' => 'A', 'qty' => 5, 'total_price' => 500]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $pidB, 'product_name' => 'B', 'qty' => 2, 'total_price' => 200]);

        $top = $this->service->topProducts($tenant, months: 6, limit: 5);

        $this->assertCount(2, $top);
        $this->assertSame('A', $top[0]['product_name']);
        $this->assertSame(5, $top[0]['total_qty']);
    }

    public function test_top_products_excludes_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $orderA  = Order::factory()->forTenant($tenantA)->create();
        $orderB  = Order::factory()->forTenant($tenantB)->create();
        OrderItem::factory()->create(['order_id' => $orderA->id, 'product_name' => 'A', 'qty' => 1, 'total_price' => 100]);
        OrderItem::factory()->create(['order_id' => $orderB->id, 'product_name' => 'B', 'qty' => 1, 'total_price' => 100]);

        $top = $this->service->topProducts($tenantA);

        $this->assertCount(1, $top);
        $this->assertSame('A', $top[0]['product_name']);
    }

    public function test_monthly_trend_returns_rows_per_driver(): void
    {
        $tenant = Tenant::factory()->create();
        Order::factory()->forTenant($tenant)->count(3)->create();

        $trend = $this->service->monthlyTrend($tenant, months: 12);

        $this->assertNotEmpty($trend);
        $this->assertArrayHasKey('month', $trend[0]);
        $this->assertArrayHasKey('total', $trend[0]);
        $this->assertArrayHasKey('order_count', $trend[0]);
    }

    public function test_credit_snapshot_returns_balance_and_recent_ledger(): void
    {
        $tenant = Tenant::factory()->create(['credit_limit' => 5000, 'current_balance' => 1500]);
        TenantCreditLedger::create([
            'tenant_id'     => $tenant->id,
            'type'          => 'debit',
            'amount'        => 1500,
            'reason'        => 'order',
            'balance_after' => 1500,
        ]);

        $snap = $this->service->creditSnapshot($tenant);

        $this->assertSame(5000.0, $snap['credit_limit']);
        $this->assertSame(1500.0, $snap['current_balance']);
        $this->assertSame(3500.0, $snap['available_credit']);
        $this->assertCount(1, $snap['recent_ledger']);
        $this->assertSame('order', $snap['recent_ledger'][0]['reason']);
    }
}
