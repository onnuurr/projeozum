<?php

namespace Tests\Feature\Marketplace;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Product\Models\Product;
use Modules\Marketplace\Models\MarketplaceCommissionRate;
use Modules\Marketplace\Models\MarketplaceExpense;
use Modules\Marketplace\Models\MarketplaceSale;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Models\TenantMarketplaceCredential;
use Modules\Marketplace\Services\AbstractMarketplaceService;
use Modules\Marketplace\Services\DTOs\MarketplaceOrderDTO;
use Modules\Marketplace\Services\DTOs\MarketplaceOrderLineDTO;
use Modules\Marketplace\Services\DTOs\PushResult;
use Modules\Marketplace\Services\DTOs\ReportResult;
use Tests\TestCase;

class AbstractMarketplaceServiceRecordSaleTest extends TestCase
{
    use RefreshDatabase;

    private function makeService(TenantMarketplaceCredential $cred): AbstractMarketplaceService
    {
        return new class($cred) extends AbstractMarketplaceService {
            public function code(): string { return 'trendyol'; }
            public function pushProduct(Product $product): PushResult { return new PushResult(true, 1); }
            public function fetchOrders(\DateTimeInterface $since): Collection { return collect(); }
            public function fetchReports(\DateTimeInterface $from, \DateTimeInterface $to): ReportResult {
                return new ReportResult(\DateTimeImmutable::createFromInterface($from), \DateTimeImmutable::createFromInterface($to), 0, 0, 0);
            }
            public function verifyWebhook(Request $request): bool { return true; }
        };
    }

    private function setupTenantCred(): TenantMarketplaceCredential
    {
        $tenant = Tenant::factory()->create();

        return TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'supplier_id' => '12345',
            'is_active'   => true,
        ]);
    }

    public function test_record_sale_upserts_by_external_ids(): void
    {
        $cred = $this->setupTenantCred();
        $service = $this->makeService($cred);

        $order = new MarketplaceOrderDTO(
            marketplace: 'trendyol',
            externalOrderId: 'TY-100',
            status: 'new',
            soldAt: new DateTimeImmutable('2026-06-15 12:00:00'),
            lines: [],
        );
        $line = new MarketplaceOrderLineDTO(
            externalLineId: 'L-1',
            productId: null,
            sku: 'SKU-1',
            productName: 'Bluz',
            soldPrice: 100.00,
            qty: 2,
            commission: 15.00,
            shippingFee: 5.00,
        );

        $service->recordSale($order, $line);

        // Aynı external_id ile tekrar — upsert; duplicate yazılmaz.
        $service->recordSale($order, $line);

        $this->assertSame(1, MarketplaceSale::count());
        $sale = MarketplaceSale::first();
        $this->assertSame('TY-100', $sale->external_order_id);
        $this->assertEqualsWithDelta(180.0, (float) $sale->net_revenue, 0.01); // 200 - 15 - 5

        // Expense satırları (commission + shipping) — yeniden yazılır, dublike yok.
        $this->assertSame(2, MarketplaceExpense::where('marketplace_sale_id', $sale->id)->count());
    }

    public function test_record_sale_falls_back_to_commission_lookup_when_dto_missing(): void
    {
        $cred = $this->setupTenantCred();

        // Default trendyol komisyonu seed (18%).
        MarketplaceCommissionRate::create([
            'marketplace'     => 'trendyol',
            'category_id'     => null,
            'commission_rate' => 18.0,
            'shipping_rate'   => 4.0,
            'valid_from'      => '2026-01-01',
        ]);

        // Bir product oluştur (commission lookup category_id'ye bakar; null döndüğü için default rate seçilir).
        $categoryId = \DB::table('product_categories')->insertGetId([
            'name' => 'Cat', 'slug' => 'cat-' . uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $pid = \DB::table('products')->insertGetId([
            'name' => 'X', 'slug' => 'x-' . uniqid(), 'sku' => 'SKU-' . uniqid(),
            'price' => 100, 'category_id' => $categoryId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = $this->makeService($cred);
        $order = new MarketplaceOrderDTO('trendyol', 'TY-200', 'new', new DateTimeImmutable(), []);
        $line = new MarketplaceOrderLineDTO('L-1', $pid, 'SKU-X', 'X', 100.0, 1); // commission=0, shipping=0

        $sale = $service->recordSale($order, $line);

        // 100 * %18 = 18 commission; 100 * %4 = 4 shipping; net = 78
        $this->assertEqualsWithDelta(18.0, (float) $sale->commission, 0.01);
        $this->assertEqualsWithDelta(4.0, (float) $sale->shipping_fee, 0.01);
        $this->assertEqualsWithDelta(78.0, (float) $sale->net_revenue, 0.01);
    }

    public function test_sync_log_lifecycle(): void
    {
        $cred = $this->setupTenantCred();
        $service = $this->makeService($cred);

        $log = $service->startSyncLog('pull_orders');
        $this->assertSame('running', $log->status);

        $service->finishSyncLog($log, ['items_processed' => 5]);
        $log->refresh();
        $this->assertSame('success', $log->status);
        $this->assertSame(5, (int) $log->items_processed);
        $this->assertNotNull($log->finished_at);

        $log2 = $service->startSyncLog('webhook');
        $service->failSyncLog($log2, 'HMAC mismatch');
        $log2->refresh();
        $this->assertSame('failed', $log2->status);
        $this->assertStringContainsString('HMAC', $log2->error_message);
    }
}
