<?php

namespace Modules\Tenant\Services\Marketplace\DTOs;

final class PushResult
{
    /**
     * @param array<string,string> $externalIds product_id => provider listing id
     * @param array<int,string> $errors product_id => hata mesajı
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $pushed,
        public readonly array $externalIds = [],
        public readonly array $errors = [],
    ) {}
}
