<?php

namespace Modules\Tenant\Services\Marketplace\DTOs;

use DateTimeImmutable;

final class ReportResult
{
    /**
     * @param array<int,array{type:string,amount:float,description:?string,occurred_at:DateTimeImmutable}> $expenses
     */
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly float $totalRevenue,
        public readonly float $totalCommission,
        public readonly float $totalShipping,
        public readonly array $expenses = [],
    ) {}
}
