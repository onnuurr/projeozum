<?php

namespace Modules\Marketplace\Services;

use Modules\Marketplace\Contracts\MarketplaceClient;
use Modules\Marketplace\DTOs\MarketplaceCredentials;

/**
 * Provider adapter'ları için saf temel sınıf. Persistence (sale/expense/sync-log kaydı)
 * içermez — bu sorumluluk çağıran modülde kalır (Tenant'ta TenantMarketplaceSyncService).
 */
abstract class AbstractMarketplaceClient implements MarketplaceClient
{
    public function __construct(
        private readonly MarketplaceCredentials $credentials,
    ) {}

    protected function credentials(): MarketplaceCredentials
    {
        return $this->credentials;
    }
}
