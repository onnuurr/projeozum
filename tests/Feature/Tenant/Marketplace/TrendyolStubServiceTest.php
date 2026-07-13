<?php

namespace Tests\Feature\Tenant\Marketplace;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\DTOs\MarketplaceCredentials;
use Modules\Marketplace\Services\Trendyol\TrendyolService;
use Modules\Marketplace\Services\Trendyol\TrendyolStubService;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;
use Modules\Tenant\Services\Marketplace\MarketplaceClientGateway;
use Tests\TestCase;

class TrendyolStubServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stub_fetch_orders_returns_fixture_dtos(): void
    {
        $credentials = new MarketplaceCredentials('trendyol', 'SUP-1', null, null);
        $service = new TrendyolStubService($credentials);
        $orders = $service->fetchOrders(new DateTimeImmutable('-1 day'));

        $this->assertCount(2, $orders);
        $this->assertSame('TY-STUB-100', $orders[0]->externalOrderId);
        $this->assertCount(1, $orders[0]->lines);
        $this->assertEquals(149.90, $orders[0]->lines[0]->soldPrice);
    }

    public function test_gateway_returns_stub_in_stub_driver(): void
    {
        config(['marketplace.driver' => 'stub']);

        $tenant = Tenant::factory()->create();
        TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'is_active'   => true,
        ]);

        $gateway = app(MarketplaceClientGateway::class);
        $client = $gateway->resolveClient($tenant, 'trendyol');

        $this->assertInstanceOf(TrendyolStubService::class, $client);
        $this->assertSame('trendyol', $client->code());
    }

    public function test_gateway_returns_live_service_in_live_driver(): void
    {
        config(['marketplace.driver' => 'live']);

        $tenant = Tenant::factory()->create();
        TenantMarketplaceCredential::create([
            'tenant_id'   => $tenant->id,
            'marketplace' => 'trendyol',
            'is_active'   => true,
        ]);

        $gateway = app(MarketplaceClientGateway::class);
        $client = $gateway->resolveClient($tenant, 'trendyol');

        $this->assertInstanceOf(TrendyolService::class, $client);
    }
}
