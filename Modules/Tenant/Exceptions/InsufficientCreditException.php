<?php

namespace Modules\Tenant\Exceptions;

use DomainException;
use Modules\Tenant\Models\Tenant;
use Throwable;

class InsufficientCreditException extends DomainException
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly float $requestedAmount,
        public readonly float $availableCredit,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Tenant #%d için yetersiz kredi: %.2f istendi, kullanılabilir %.2f ₺.',
                $tenant->id,
                $requestedAmount,
                $availableCredit,
            ),
            0,
            $previous,
        );
    }
}
