<?php

namespace Modules\Product\Exceptions;

use RuntimeException;

/**
 * Ürün durum makinesinde (Faz 2) harita dışı bir geçiş denendiğinde fırlatılır.
 * Controller katmanı bunu validation hatasına/flash toast'a çevirir.
 */
class InvalidProductTransitionException extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct(sprintf(
            '"%s" durumundan "%s" durumuna geçiş yapılamaz.',
            $from,
            $to,
        ));
    }
}
