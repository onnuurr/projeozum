<?php

namespace Modules\Tenant\Services\Marketplace;

use Modules\Marketplace\Contracts\MarketplaceClient;
use Modules\Marketplace\DTOs\MarketplaceCredentials;
use Modules\Marketplace\Services\MarketplaceClientFactory;
use Modules\Tenant\Models\Tenant;
use Modules\Tenant\Models\TenantMarketplaceCredential;

/**
 * Tenant'ın DB'de sakladığı TenantMarketplaceCredential'ı Marketplace modülünün saf
 * MarketplaceCredentials DTO'suna çevirip doğru MarketplaceClient'ı üretir.
 *
 * Eski MarketplaceServiceResolver'ın yerini alır — artık sadece client döner, persistence
 * yapmaz (bkz. TenantMarketplaceSyncService).
 */
class MarketplaceClientGateway
{
    public function __construct(private MarketplaceClientFactory $factory) {}

    public function resolveClient(Tenant $tenant, string $marketplace): MarketplaceClient
    {
        $credential = TenantMarketplaceCredential::query()
            ->where('tenant_id', $tenant->id)
            ->where('marketplace', $marketplace)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->factory->make($marketplace, $this->toCredentialsDto($credential));
    }

    /**
     * Elde zaten bulunan bir TenantMarketplaceCredential'dan doğrudan client üretir
     * (ör. webhook akışı — tenant/marketplace'ten yeniden sorgu atmaya gerek yok).
     */
    public function makeFromCredential(TenantMarketplaceCredential $credential): MarketplaceClient
    {
        return $this->factory->make($credential->marketplace, $this->toCredentialsDto($credential));
    }

    public function toCredentialsDto(TenantMarketplaceCredential $credential): MarketplaceCredentials
    {
        return new MarketplaceCredentials(
            marketplace: $credential->marketplace,
            accountId: $credential->supplier_id,
            apiKey: $credential->api_key,
            apiSecret: $credential->api_secret,
            storeName: $credential->store_name,
        );
    }
}
