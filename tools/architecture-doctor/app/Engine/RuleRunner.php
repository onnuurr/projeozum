<?php

namespace ArchitectureDoctor\Engine;

use ArchitectureDoctor\Report\RuleResult;

final class RuleRunner
{
    public function __construct(private readonly RuleDiscovery $discovery) {}

    /**
     * @return RuleResult[]
     */
    public function run(): array
    {
        return array_map(
            function (string $ruleClass): RuleResult {
                $rule = new $ruleClass;

                return new RuleResult(
                    ruleId: $rule->id(),
                    category: $rule->category(),
                    severity: $rule->severity(),
                    lifecycle: $rule->lifecycle(),
                    findings: $rule->check(),
                );
            },
            $this->discovery->discover(),
        );
    }
}
