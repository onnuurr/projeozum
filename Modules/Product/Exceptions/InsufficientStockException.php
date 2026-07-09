<?php

namespace Modules\Product\Exceptions;

use RuntimeException;

/**
 * Sipariş anındaki stok tahsisi, bir varyant için tüm depolardaki kullanılabilir
 * miktarı aştığında fırlatılır. Controller katmanı bunu flash toast'a çevirir.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf(
            'Varyant #%d için yeterli stok yok (istenen: %d, mevcut: %d).',
            $variantId,
            $requested,
            $available,
        ));
    }
}
