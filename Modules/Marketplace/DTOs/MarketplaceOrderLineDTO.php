<?php

namespace Modules\Marketplace\DTOs;

/**
 * Provider-agnostic order satırı. Adapter'lar provider payload'unu bu DTO'ya map'ler.
 *
 * NOT: productId burada kasıtlı olarak Marketplace tarafında hiç çözülmez (bu modül
 * Product/Tenant Eloquent modeline bağımlı olamaz) — sku üzerinden productId eşlemesi
 * çağıran tarafta (Modules\Tenant\Services\Marketplace\TenantMarketplaceSyncService) yapılır.
 */
final class MarketplaceOrderLineDTO
{
    public function __construct(
        public readonly string $externalLineId,
        public readonly ?int $productId,       // Marketplace tarafında her zaman null gelir
        public readonly ?string $sku,
        public readonly string $productName,
        public readonly float $soldPrice,
        public readonly int $qty,
        public readonly float $commission = 0,
        public readonly float $shippingFee = 0,
        public readonly array $raw = [],       // Ham provider satır payload'u (debug için)
    ) {}

    public function netRevenue(): float
    {
        return ($this->soldPrice * $this->qty) - $this->commission - $this->shippingFee;
    }
}
