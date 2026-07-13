<?php

namespace Modules\Marketplace\Services;

use InvalidArgumentException;
use Modules\Marketplace\Contracts\MarketplaceClient;
use Modules\Marketplace\DTOs\MarketplaceCredentials;

/**
 * Provider başına ayrı service ağacı + driver (live/stub) match'i.
 *
 * Generic factory yerine match expression — her satır net + IDE go-to-definition çalışır.
 * DB'ye veya herhangi bir Tenant/Product modeline dokunmaz — çağıran taraf credential'ı
 * DTO olarak hazırlayıp verir (bkz. Modules\Tenant\Services\Marketplace\MarketplaceClientGateway).
 */
class MarketplaceClientFactory
{
    public function make(string $marketplace, MarketplaceCredentials $credentials, ?string $driver = null): MarketplaceClient
    {
        $driver ??= config('marketplace.driver', 'stub');

        return match ([$marketplace, $driver]) {
            ['trendyol', 'live']    => new Trendyol\TrendyolService($credentials),
            ['trendyol', 'stub']    => new Trendyol\TrendyolStubService($credentials),
            ['hepsiburada', 'live'] => new Hepsiburada\HepsiburadaService($credentials),
            ['hepsiburada', 'stub'] => new Hepsiburada\HepsiburadaStubService($credentials),
            ['n11', 'live']         => new N11\N11Service($credentials),
            ['n11', 'stub']         => new N11\N11StubService($credentials),
            ['ciceksepeti', 'live'] => new Ciceksepeti\CiceksepetiService($credentials),
            ['ciceksepeti', 'stub'] => new Ciceksepeti\CiceksepetiStubService($credentials),
            default => throw new InvalidArgumentException(
                "Bilinmeyen marketplace/driver kombinasyonu: {$marketplace}/{$driver}",
            ),
        };
    }
}
