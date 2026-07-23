<?php

namespace Tests\Unit\ArchitectureDoctor\Quality;

use ArchitectureDoctor\Rules\Quality\UnusedPublicServiceHeuristicRule;
use Tests\TestCase;

class UnusedPublicServiceHeuristicRuleTest extends TestCase
{
    private const SERVICES = __DIR__.'/../Fixtures/Quality/Services';

    private const SEARCH = __DIR__.'/../Fixtures/Quality';

    public function test_it_flags_a_service_referenced_once_or_less_but_not_a_well_used_one(): void
    {
        $rule = new UnusedPublicServiceHeuristicRule([self::SERVICES], [self::SEARCH]);

        $findings = $rule->check();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('UnusedService', $findings[0]->message);
        $this->assertStringContainsString('UnusedService.php', (string) $findings[0]->file);
    }
}
