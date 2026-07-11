<?php

namespace Modules\Marketplace\Exceptions;

use RuntimeException;

/**
 * Marketplace provider için live entegrasyon henüz implement edilmedi.
 * Adapter iskelet methodları bu hatayı fırlatır; UI bu noktada disable + tooltip gösterir.
 */
class NotSupportedException extends RuntimeException
{
    public static function liveDriverMissing(string $marketplace, string $operation): self
    {
        return new self(
            sprintf('%s için live entegrasyon henüz aktif değil (operation: %s).', $marketplace, $operation),
        );
    }
}
