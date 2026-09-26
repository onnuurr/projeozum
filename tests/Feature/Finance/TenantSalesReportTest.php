<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Finance\Exports\TenantSalesReportExport;
use Modules\Finance\Services\TenantPurchaseReportService;
use Modules\Product\Models\Order;
use Modules\Product\Models\OrderItem;
use Modules\Product\Models\OrderReturn;
use Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Finans › Bayi Satış Raporu: filtreler, bayi/kanal/ürün kırılımı, iade düşülmüş net ciro, Excel.
 */
class TenantSalesReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alfa;
    private Tenant $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alfa = Tenant::factory()->create(['name' => 'Alfa Butik']);
        $this->beta = Tenant::factory()->create(['name' => 'Beta Kids']);

        // Alfa: kendi stoğu için 1.000, Trendyol dropship 500; Beta: kanal belirtilmemiş dropship 300 + iptal 999.
        $this->order($this->alfa, 1000, '2026-09-01', null, 'Tulum');
        $trendyol = $this->order($this->alfa, 500, '2026-09-10', ['channel' => 'trendyol'], 'Body');
        $this->order($this->beta, 300, '2026-09-15', ['carrier' => 'yurtici'], 'Body');
        $this->order($this->beta, 999, '2026-09-16', null, 'Tulum', Order::STATUS_CANCELLED);

        OrderReturn::create([
            'order_id' => $trendyol->id,
            'tenant_id' => $this->alfa->id,
            'bagisto_rma_id' => '1',
            'bagisto_order_id' => $trendyol->bagisto_order_id,
            'status' => OrderReturn::STATUS_REFUNDED,
            'approved_amount' => 100,
        ]);
    }

    private function order(Tenant $tenant, float $total, string $date, ?array $dropship, string $product, string $status = Order::STATUS_PENDING): Order
    {
        $order = Order::factory()->create([
            'tenant_id' => $tenant->id,
            'bagisto_order_id' => (string) random_int(1000, 999999),
            'order_type' => Order::TYPE_DROPSHIP,
            'shipping_info' => ['source' => 'bagisto', 'dropship' => $dropship],
            'subtotal' => $total,
            'total' => $total,
            'status' => $status,
        ]);
        $order->forceFill(['created_at' => $date.' 12:00:00'])->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => $product,
            'product_brand' => 'Ozm Kids',
            'qty' => 2,
            'unit_price' => $total / 2,
            'total_price' => $total,
        ]);

        return $order;
    }

    private function report(): TenantPurchaseReportService
    {
        return app(TenantPurchaseReportService::class);
    }

    public function test_summary_excludes_cancelled_and_subtracts_refunds(): void
    {
        $summary = $this->report()->summary();

        $this->assertSame(4, $summary['orderCount']);
        $this->assertEquals(1800.0, $summary['grossAmount']);
        $this->assertEquals(100.0, $summary['refundedAmount']);
        $this->assertEquals(1700.0, $summary['netAmount']);
        $this->assertSame(2, $summary['tenantCount']);
        // Dropship (500 + 300) / 1800
        $this->assertEquals(44.4, $summary['dropshipShare']);
    }

    public function test_by_tenant_has_return_rate(): void
    {
        $alfa = $this->report()->byTenant()->firstWhere('tenantId', $this->alfa->id);

        $this->assertEquals(1500.0, $alfa['grossAmount']);
        $this->assertEquals(1400.0, $alfa['netAmount']);
        $this->assertEquals(6.7, $alfa['returnRate']);
    }

    public function test_channel_breakdown_separates_self_stock_and_dropship_channels(): void
    {
        $channels = $this->report()->byChannel()->keyBy('channel');

        $this->assertEquals(1000.0, $channels[TenantPurchaseReportService::CHANNEL_SELF]['grossAmount']);
        $this->assertEquals(500.0, $channels['trendyol']['grossAmount']);
        $this->assertEquals(300.0, $channels[TenantPurchaseReportService::CHANNEL_DROPSHIP_UNSPECIFIED]['grossAmount']);
    }

    public function test_filters_combine(): void
    {
        $report = $this->report();

        $this->assertSame(2, $report->orders(['tenant_id' => $this->alfa->id])->count());
        $this->assertSame(1, $report->orders(['channel' => 'trendyol'])->count());
        $this->assertSame(2, $report->orders(['date_from' => '2026-09-10', 'date_to' => '2026-09-15'])->count());
        $this->assertSame(1, $report->orders(['product' => 'tulum', 'status' => Order::STATUS_PENDING])->count());
        $this->assertEquals(['Body'], $report->byProduct(['product' => 'body'])->pluck('productName')->all());
    }

    public function test_page_and_excel_export_need_finance_view(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'finance.view', 'guard_name' => 'web']);

        $this->actingAs($user)->get('/finance/tenant-purchases')->assertForbidden();

        $user->givePermissionTo('finance.view');

        $this->actingAs($user)->get('/finance/tenant-purchases?channel=trendyol')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Finance::TenantPurchases', false)
                ->where('summary.grossAmount', 500)
                ->has('byTenant', 1)
                ->where('filters.channel', 'trendyol'));

        Excel::fake();
        $this->actingAs($user)->get('/finance/tenant-purchases/export?tenant_id='.$this->beta->id)->assertOk();
        Excel::assertDownloaded('bayi-satis-raporu-'.now()->format('Y-m-d').'.xlsx', function (TenantSalesReportExport $export) {
            $sheets = $export->sheets();

            return count($sheets) === 4 && count($sheets[3]->array()) === 2;
        });
    }
}
