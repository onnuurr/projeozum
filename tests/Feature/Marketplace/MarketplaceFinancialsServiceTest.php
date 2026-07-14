<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Models\MarketplaceExpense;
use Modules\Marketplace\Models\MarketplaceSale;
use Modules\Marketplace\Services\Contracts\MarketplaceFinancialsContract;
use Modules\Tenant\Models\Tenant;
use Tests\TestCase;

class MarketplaceFinancialsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_total_sums_price_times_qty_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        MarketplaceSale::create([
            'tenant_id' => $tenant->id, 'marketplace' => 'trendyol',
            'external_order_id' => 'TY-1', 'external_line_id' => 'L-1',
            'sold_price' => 100, 'qty' => 2, 'net_revenue' => 180,
            'status' => 'delivered', 'sold_at' => now()->subDays(5),
        ]);

        $svc = app(MarketplaceFinancialsContract::class);
        $total = $svc->salesTotal($tenant->id, now()->subMonth(), now()->addDay());

        $this->assertSame(200.0, $total);
    }

    public function test_expenses_total_sums_amount_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        MarketplaceExpense::create([
            'tenant_id' => $tenant->id, 'marketplace' => 'trendyol',
            'expense_type' => 'commission', 'amount' => 20,
            'occurred_at' => now()->subDays(5),
        ]);

        $svc = app(MarketplaceFinancialsContract::class);
        $total = $svc->expensesTotal($tenant->id, now()->subMonth(), now()->addDay());

        $this->assertSame(20.0, $total);
    }

    public function test_commission_and_shipping_rates_delegates_to_lookup(): void
    {
        \Modules\Marketplace\Models\MarketplaceCommissionRate::create([
            'marketplace' => 'trendyol', 'category_id' => null,
            'commission_rate' => 15.0, 'shipping_rate' => 3.0,
            'valid_from' => '2026-01-01',
        ]);

        $svc = app(MarketplaceFinancialsContract::class);
        [$commission, $shipping] = $svc->commissionAndShippingRates('trendyol', 999999);

        $this->assertSame(15.0, $commission);
        $this->assertSame(3.0, $shipping);
    }
}
