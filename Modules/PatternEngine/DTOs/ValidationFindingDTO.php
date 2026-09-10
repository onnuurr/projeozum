<?php

namespace Modules\PatternEngine\DTOs;

/**
 * mimari doküman §7.1 `Finding` — `False` dönmek yasak (§P4): her başarısızlık
 * ölçülen değer, beklenen değer ve farkla birlikte döner.
 */
final class ValidationFindingDTO
{
    public function __construct(
        public readonly string $ruleId,
        public readonly bool $passed,
        public readonly float $measured,
        public readonly float $expected,
        public readonly float $delta,
        public readonly float $tolerance,
        public readonly string $message,
        public readonly ?string $suggestion,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ruleId: (string) $data['rule_id'],
            passed: (bool) $data['passed'],
            measured: (float) $data['measured'],
            expected: (float) $data['expected'],
            delta: (float) $data['delta'],
            tolerance: (float) $data['tolerance'],
            message: (string) $data['message'],
            suggestion: isset($data['suggestion']) ? (string) $data['suggestion'] : null,
        );
    }
}
