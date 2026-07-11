<?php

namespace Modules\Marketplace\Services\DTOs;

use DateTimeImmutable;

final class MarketplaceOrderDTO
{
    /**
     * @param MarketplaceOrderLineDTO[] $lines
     */
    public function __construct(
        public readonly string $marketplace,            // 'trendyol' vs.
        public readonly string $externalOrderId,
        public readonly string $status,                 // new/shipped/delivered/cancelled/returned
        public readonly DateTimeImmutable $soldAt,
        public readonly array $lines,
        public readonly array $raw = [],
    ) {}
}
