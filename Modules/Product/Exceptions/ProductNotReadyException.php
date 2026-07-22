<?php

namespace Modules\Product\Exceptions;

use Modules\Product\Enums\Capability;
use RuntimeException;

/**
 * `ProductReadinessService::assertReady()` ürün ilgili capability için hazır
 * değilse fırlatır (Faz 4). Çağıran modül bunu kendi HTTP sınırında yakalayıp
 * dönüştürür — Product bu exception'ı kimseye zorla dayatmaz, yalnızca
 * sorulduğunda cevap verir.
 */
class ProductNotReadyException extends RuntimeException
{
    public function __construct(
        public readonly Capability $capability,
        public readonly array $missing,
    ) {
        parent::__construct(sprintf(
            'Ürün "%s" için hazır değil: %s',
            $capability->label(),
            implode(', ', $missing),
        ));
    }
}
