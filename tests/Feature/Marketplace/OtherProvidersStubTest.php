<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Marketplace\Exceptions\NotSupportedException;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Models\TenantMarketplaceCredential;
use Modules\Marketplace\Services\Ciceksepeti\CiceksepetiStubService;
use Modules\Marketplace\Services\Hepsiburada\HepsiburadaService;
use Modules\Marketplace\Services\Hepsiburada\HepsiburadaStubService;
use Modules\Marketplace\Services\N11\N11Service;
use Modules\Marketplace\Services\N11\N11StubService;
use Tests\TestCase;

class OtherProvidersStubTest extends TestCase
{
    use RefreshDatabase;

    private function cred(string $marketplace): TenantMarketplaceCredential
    {
        return TenantMarketplaceCredential::create([
            'tenant_id'   => Tenant::factory()->create()->id,
            'marketplace' => $marketplace,
            'is_active'   => true,
        ]);
    }

    public function test_hepsiburada_stub_returns_success(): void
    {
        $service = new HepsiburadaStubService($this->cred('hepsiburada'));
        $this->assertSame('hepsiburada', $service->code());
        $this->assertTrue($service->fetchOrders(new \DateTimeImmutable('-1 day'))->isEmpty());
    }

    public function test_hepsiburada_live_throws_not_supported(): void
    {
        $service = new HepsiburadaService($this->cred('hepsiburada'));

        $this->expectException(NotSupportedException::class);
        $service->fetchOrders(new \DateTimeImmutable('-1 day'));
    }

    public function test_n11_stub_returns_success(): void
    {
        $service = new N11StubService($this->cred('n11'));
        $this->assertSame('n11', $service->code());
    }

    public function test_n11_live_throws_not_supported(): void
    {
        $this->expectException(NotSupportedException::class);
        (new N11Service($this->cred('n11')))->fetchOrders(new \DateTimeImmutable());
    }

    public function test_ciceksepeti_stub_returns_success(): void
    {
        $service = new CiceksepetiStubService($this->cred('ciceksepeti'));
        $this->assertSame('ciceksepeti', $service->code());
    }
}
