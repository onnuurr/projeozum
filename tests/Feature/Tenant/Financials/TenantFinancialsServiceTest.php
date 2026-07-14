<?php

namespace Tests\Feature\Tenant\Financials;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Models\MarketplaceExpense;
use Modules\Marketplace\Models\MarketplaceSale;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantInvoice;
use Modules\Tenant\Services\TenantFinancialsService;
use Tests\TestCase;

class TenantFinancialsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_sums_sales_expenses_invoices_correctly(): void
    {
        $tenant = Tenant::factory()->create();

        MarketplaceSale::create([
            'tenant_id' => $tenant->id, 'marketplace' => 'trendyol',
            'external_order_id' => 'TY-1', 'external_line_id' => 'L-1',
            'sold_price' => 100, 'qty' => 2, 'net_revenue' => 180,
            'status' => 'delivered', 'sold_at' => now()->subDays(5),
        ]);
        MarketplaceExpense::create([
            'tenant_id' => $tenant->id, 'marketplace' => 'trendyol',
            'expense_type' => 'commission', 'amount' => 20,
            'occurred_at' => now()->subDays(5),
        ]);
        TenantInvoice::factory()->create([
            'tenant_id' => $tenant->id, 'amount' => 50,
        ]);

        $svc = app(TenantFinancialsService::class);
        $sum = $svc->summary($tenant, now()->subMonth(), now()->addDay());

        $this->assertSame(200.0, $sum['sales_total']);  // 100 * 2
        $this->assertSame(20.0,  $sum['expenses_total']);
        $this->assertSame(50.0,  $sum['invoiced_total']);
        $this->assertSame(130.0, $sum['net']);          // 200 - 20 - 50
    }

    public function test_excludes_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        MarketplaceSale::create([
            'tenant_id' => $tenantA->id, 'marketplace' => 'trendyol',
            'external_order_id' => 'A-1', 'external_line_id' => 'L-1',
            'sold_price' => 100, 'qty' => 1, 'net_revenue' => 80,
            'status' => 'new', 'sold_at' => now()->subDays(2),
        ]);
        MarketplaceSale::create([
            'tenant_id' => $tenantB->id, 'marketplace' => 'trendyol',
            'external_order_id' => 'B-1', 'external_line_id' => 'L-1',
            'sold_price' => 999, 'qty' => 1, 'net_revenue' => 800,
            'status' => 'new', 'sold_at' => now()->subDays(2),
        ]);

        $svc = app(TenantFinancialsService::class);
        $sum = $svc->summary($tenantA, now()->subMonth(), now()->addDay());

        $this->assertSame(100.0, $sum['sales_total']);
        $this->assertSame(100.0 - 0 - 0, $sum['net']);
    }
}
