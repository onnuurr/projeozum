<?php

namespace Modules\Marketplace\DTOs;

final class ProductVariantPushDTO
{
    public function __construct(
        public readonly ?string $sku,
        public readonly int $stock,
        public readonly ?float $price = null,
        public readonly ?string $colorName = null,
        public readonly ?string $size = null,
    ) {}
}
