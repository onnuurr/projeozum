<?php

namespace Tests\Unit\ArchitectureDoctor\Controllers;

use ArchitectureDoctor\Rules\Controllers\LargeControllerRule;
use Tests\TestCase;

class LargeControllerRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Controllers';

    public function test_it_flags_only_the_controller_over_the_line_threshold(): void
    {
        $rule = new LargeControllerRule([self::FIXTURES]);

        $findings = $rule->check();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('LargeController', $findings[0]->message);
        $this->assertStringContainsString('LargeController.php', (string) $findings[0]->file);
        $this->assertStringContainsString('271 satır', $findings[0]->message);
    }
}
