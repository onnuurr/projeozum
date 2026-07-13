<?php

namespace Modules\Marketplace\DTOs;

/**
 * Provider-agnostic ürün push payload'u. Tenant'ın (ve Product modülünün) Eloquent
 * `Product` modelinin yerini alır — çağıran taraf (Tenant) bu DTO'yu kendi verisinden
 * assemble eder (bkz. Modules\Tenant\Services\Marketplace\TrendyolProductPayloadBuilder).
 * Projenin genel "DTO kullanılmaz" kuralının sanctioned istisnalarından biridir
 * (bkz. CLAUDE.md "DTO kullanım istisnaları").
 */
final class ProductPushDTO
{
    /**
     * @param string[] $images
     * @param ProductVariantPushDTO[] $variants
     */
    public function __construct(
        public readonly int $productId,
        public readonly ?string $sku,
        public readonly string $title,
        public readonly float $listPrice,
        public readonly float $salePrice,
        public readonly int $vatRate,
        public readonly array $images,
        public readonly array $variants,
        public readonly ?int $externalCategoryId = null,
        public readonly ?int $externalBrandId = null,
    ) {}
}
