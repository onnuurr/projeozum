<?php

namespace Tests\Feature\Marketplace;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Models\TenantMarketplaceCredential;
use Modules\Marketplace\Services\Trendyol\TrendyolStubService;
use Tests\TestCase;

class TrendyolStubServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stub_fetch_orders_returns_fixture_dtos(): void
    {
        $tenant = Tenant::factory()->create();
        $cred = TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'supplier_id' => 'SUP-1',
            'is_active'   => true,
        ]);

        $service = new TrendyolStubService($cred);
        $orders = $service->fetchOrders(new DateTimeImmutable('-1 day'));

        $this->assertCount(2, $orders);
        $this->assertSame('TY-STUB-100', $orders[0]->externalOrderId);
        $this->assertCount(1, $orders[0]->lines);
        $this->assertEquals(149.90, $orders[0]->lines[0]->soldPrice);
    }

    public function test_resolver_returns_stub_in_stub_driver(): void
    {
        config(['marketplace.driver' => 'stub']);

        $tenant = Tenant::factory()->create();
        TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'is_active'   => true,
        ]);

        $resolver = app(\Modules\Marketplace\Services\MarketplaceServiceResolver::class);
        $service = $resolver->for($tenant, 'trendyol');

        $this->assertInstanceOf(TrendyolStubService::class, $service);
        $this->assertSame('trendyol', $service->code());
    }

    public function test_resolver_returns_live_service_in_live_driver(): void
    {
        config(['marketplace.driver' => 'live']);

        $tenant = Tenant::factory()->create();
        TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'is_active'   => true,
        ]);

        $resolver = app(\Modules\Marketplace\Services\MarketplaceServiceResolver::class);
        $service = $resolver->for($tenant, 'trendyol');

        $this->assertInstanceOf(\Modules\Marketplace\Services\Trendyol\TrendyolService::class, $service);
    }
}
