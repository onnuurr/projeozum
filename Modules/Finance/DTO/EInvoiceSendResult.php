<?php

namespace Modules\Finance\DTO;

/**
 * EInvoiceProviderInterface::send() dönüş değeri. Projede genel olarak DTO
 * kullanılmaz (bkz. laravel-service skill); bu, dış entegratör sınırında tip
 * güvenliği için yapılan sanctioned istisnalardan biridir (bkz. CLAUDE.md
 * "DTO kullanım istisnaları").
 */
final class EInvoiceSendResult
{
    public function __construct(
        public readonly ?string $uuid,
        public readonly string $status,
        public readonly ?array $rawResponse = null,
    ) {
    }
}
