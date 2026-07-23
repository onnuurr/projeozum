<?php

namespace ArchitectureDoctor\Report;

use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Severity;

final class RuleResult
{
    /**
     * @param  Finding[]  $findings
     */
    public function __construct(
        public readonly string $ruleId,
        public readonly string $category,
        public readonly Severity $severity,
        public readonly Lifecycle $lifecycle,
        public readonly array $findings,
    ) {}

    public function passed(): bool
    {
        return $this->findings === [];
    }
}
