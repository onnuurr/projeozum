<?php

namespace App\DTO;

/**
 * SmsProviderInterface::send()'in dönüş değeri. Modules\Finance\DTO\EInvoiceSendResult
 * ile aynı gerekçe: dış entegratörün ham cevabını saran bir değer nesnesi,
 * bir Eloquent modelin yerine geçmiyor (bkz. CLAUDE.md "DTO kullanım istisnaları").
 */
class SmsSendResult
{
    /**
     * @param  array<string,mixed>|null  $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $orderId = null,
        public readonly ?int $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?array $rawResponse = null,
    ) {}
}
