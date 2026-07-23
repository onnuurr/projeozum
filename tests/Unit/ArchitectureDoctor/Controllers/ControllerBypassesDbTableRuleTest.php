<?php

namespace Tests\Unit\ArchitectureDoctor\Controllers;

use ArchitectureDoctor\Rules\Controllers\ControllerBypassesDbTableRule;
use Tests\TestCase;

class ControllerBypassesDbTableRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Controllers';

    public function test_it_flags_db_table_but_not_clean_or_large_controllers(): void
    {
        $rule = new ControllerBypassesDbTableRule([self::FIXTURES]);

        $findings = $rule->check();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('BypassingController', $findings[0]->message);
        $this->assertStringContainsString('BypassingController.php', (string) $findings[0]->file);
    }
}
