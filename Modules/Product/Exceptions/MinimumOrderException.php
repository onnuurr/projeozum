<?php

namespace Modules\Product\Exceptions;

use RuntimeException;

/**
 * B2B minimum sipariş kuralları ihlal edildiğinde fırlatılır (D-B2B kısıtları):
 *  - tenant `min_order_total` altında ara toplam,
 *  - satır bazında ürün `min_order_qty` altında adet,
 *  - satır adedi ürün `order_multiple` katı değil.
 *
 * `type` ihlal türünü, diğer alanlar ihlal detayını taşır. Portal controller bunu
 * validation/flash hatasına çevirir; place() transaction'ı rollback olur.
 */
class MinimumOrderException extends RuntimeException
{
    public const TYPE_TENANT_TOTAL  = 'tenant_total';
    public const TYPE_LINE_QTY      = 'line_qty';
    public const TYPE_LINE_MULTIPLE = 'line_multiple';

    public function __construct(
        public readonly string $type,
        public readonly float $required,
        public readonly float $actual,
        public readonly ?int $productId = null,
        public readonly ?string $productName = null,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $this->defaultMessage());
    }

    public static function tenantTotal(float $required, float $actual): self
    {
        return new self(
            type: self::TYPE_TENANT_TOTAL,
            required: $required,
            actual: $actual,
            message: sprintf('Minimum sipariş tutarı %.2f ₺ (sepet: %.2f ₺).', $required, $actual),
        );
    }

    public static function lineQty(int $productId, ?string $productName, int $required, int $actual): self
    {
        return new self(
            type: self::TYPE_LINE_QTY,
            required: (float) $required,
            actual: (float) $actual,
            productId: $productId,
            productName: $productName,
            message: sprintf('"%s" için minimum sipariş adedi %d (girilen: %d).', $productName ?? "#{$productId}", $required, $actual),
        );
    }

    public static function lineMultiple(int $productId, ?string $productName, int $multiple, int $actual): self
    {
        return new self(
            type: self::TYPE_LINE_MULTIPLE,
            required: (float) $multiple,
            actual: (float) $actual,
            productId: $productId,
            productName: $productName,
            message: sprintf('"%s" %d katları hâlinde sipariş edilmeli (girilen: %d).', $productName ?? "#{$productId}", $multiple, $actual),
        );
    }

    private function defaultMessage(): string
    {
        return 'Minimum sipariş kuralı ihlal edildi.';
    }
}
