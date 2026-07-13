<?php

namespace Modules\Marketplace\DTOs;

/**
 * Provider-agnostic API kimlik bilgisi. Tenant'ın TenantMarketplaceCredential Eloquent
 * modelinin yerini alır — Marketplace modülü hiçbir Eloquent modeline bağımlı olmamalı.
 * Projenin genel "DTO kullanılmaz" kuralının sanctioned istisnalarından biridir
 * (bkz. CLAUDE.md "DTO kullanım istisnaları").
 *
 * $accountId provider'a göre farklı adlandırılan aynı kavramdır:
 *   Trendyol → supplierId, Hepsiburada → merchantId, N11 → companyId, Çiçeksepeti → sellerId.
 */
final class MarketplaceCredentials
{
    public function __construct(
        public readonly string $marketplace,
        public readonly ?string $accountId,
        public readonly ?string $apiKey,
        public readonly ?string $apiSecret,
        public readonly ?string $storeName = null,
    ) {}
}
