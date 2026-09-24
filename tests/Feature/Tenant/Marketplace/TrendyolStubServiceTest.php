<?php

namespace Tests\Feature\Tenant\Marketplace;

use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\DTOs\MarketplaceCredentials;
use Modules\Marketplace\Services\Trendyol\TrendyolStubService;
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
}
