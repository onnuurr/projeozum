<?php

namespace Modules\Finance\DTO;

final class EInvoiceStatusResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?array $rawResponse = null,
    ) {
    }
}
