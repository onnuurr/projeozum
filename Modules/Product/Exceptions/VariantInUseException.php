<?php

namespace Modules\Product\Exceptions;

use RuntimeException;

/**
 * Ürün düzenlemesinde formdan çıkarılan bir varyant silinemediğinde fırlatılır: stoku /
 * stok hareket geçmişi var ya da başka bir kayıtta (ör. Atölye üretim emri) kullanılıyor.
 * Controller katmanı bunu `variants` alanına validation hatası olarak çevirir.
 */
class VariantInUseException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        string $reason,
    ) {
        parent::__construct(sprintf('"%s" varyantı silinemez: %s.', $sku, $reason));
    }
}
