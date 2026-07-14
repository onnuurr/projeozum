<?php

namespace Modules\Marketplace\Services;

use InvalidArgumentException;
use Modules\Tenant\Models\Tenant;
use Modules\Marketplace\Models\TenantMarketplaceCredential;
use Modules\Marketplace\Services\Contracts\MarketplaceClient;

/**
 * Provider başına ayrı service ağacı + driver (live/stub) match'i.
 *
 * Generic factory yerine match expression — her satır net + IDE go-to-definition çalışır.
 * Yeni marketplace ekleyince burası ve match expression aynı anda güncellenir (compile-time
 * exhaustiveness yok ama PR review'da göz kaçırması azalır).
 */
class MarketplaceServiceResolver
{
    public function for(Tenant $tenant, string $marketplace): MarketplaceClient
    {
        $credential = TenantMarketplaceCredential::query()
            ->where('tenant_id', $tenant->id)
            ->where('marketplace', $marketplace)
            ->where('is_active', true)
            ->firstOrFail();

        $driver = config('marketplace.driver', 'stub');

        return match ([$marketplace, $driver]) {
            ['trendyol', 'live']    => new Trendyol\TrendyolService($credential),
            ['trendyol', 'stub']    => new Trendyol\TrendyolStubService($credential),
            ['hepsiburada', 'live'] => new Hepsiburada\HepsiburadaService($credential),
            ['hepsiburada', 'stub'] => new Hepsiburada\HepsiburadaStubService($credential),
            ['n11', 'live']         => new N11\N11Service($credential),
            ['n11', 'stub']         => new N11\N11StubService($credential),
            ['ciceksepeti', 'live'] => new Ciceksepeti\CiceksepetiService($credential),
            ['ciceksepeti', 'stub'] => new Ciceksepeti\CiceksepetiStubService($credential),
            default => throw new InvalidArgumentException(
                "Bilinmeyen marketplace/driver kombinasyonu: {$marketplace}/{$driver}",
            ),
        };
    }
}
