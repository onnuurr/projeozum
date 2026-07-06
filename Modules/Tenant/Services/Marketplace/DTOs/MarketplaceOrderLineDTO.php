<?php

namespace Modules\Tenant\Services\Marketplace\DTOs;

/**
 * Provider-agnostic order satırı. Adapter'lar provider payload'unu bu DTO'ya map'ler.
 */
final class MarketplaceOrderLineDTO
{
    public function __construct(
        public readonly string $externalLineId,
        public readonly ?int $productId,       // Local Product::id veya null (eşleşmediyse)
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
