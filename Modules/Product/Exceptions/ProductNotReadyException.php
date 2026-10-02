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
        parent::__construct(__('product::messages.not_ready', [
            'capability' => $capability->label(),
            'missing' => implode(', ', $missing),
        ]));
    }
}
