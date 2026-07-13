<?php

namespace Tests\Feature\Tenant\Marketplace;

use DateTimeImmutable;
use Modules\Marketplace\DTOs\MarketplaceCredentials;
use Modules\Marketplace\Exceptions\NotSupportedException;
use Modules\Marketplace\Services\Ciceksepeti\CiceksepetiStubService;
use Modules\Marketplace\Services\Hepsiburada\HepsiburadaService;
use Modules\Marketplace\Services\Hepsiburada\HepsiburadaStubService;
use Modules\Marketplace\Services\N11\N11Service;
use Modules\Marketplace\Services\N11\N11StubService;
use Tests\TestCase;

class OtherProvidersStubTest extends TestCase
{
    private function creds(string $marketplace): MarketplaceCredentials
    {
        return new MarketplaceCredentials($marketplace, null, null, null);
    }

    public function test_hepsiburada_stub_returns_success(): void
    {
        $service = new HepsiburadaStubService($this->creds('hepsiburada'));
        $this->assertSame('hepsiburada', $service->code());
        $this->assertTrue($service->fetchOrders(new DateTimeImmutable('-1 day'))->isEmpty());
    }

    public function test_hepsiburada_live_throws_not_supported(): void
    {
        $service = new HepsiburadaService($this->creds('hepsiburada'));

        $this->expectException(NotSupportedException::class);
        $service->fetchOrders(new DateTimeImmutable('-1 day'));
    }

    public function test_n11_stub_returns_success(): void
    {
        $service = new N11StubService($this->creds('n11'));
        $this->assertSame('n11', $service->code());
    }

    public function test_n11_live_throws_not_supported(): void
    {
        $this->expectException(NotSupportedException::class);
        (new N11Service($this->creds('n11')))->fetchOrders(new DateTimeImmutable());
    }

    public function test_ciceksepeti_stub_returns_success(): void
    {
        $service = new CiceksepetiStubService($this->creds('ciceksepeti'));
        $this->assertSame('ciceksepeti', $service->code());
    }
}
