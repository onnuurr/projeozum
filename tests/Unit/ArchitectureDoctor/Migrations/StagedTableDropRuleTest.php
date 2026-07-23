<?php

namespace Tests\Unit\ArchitectureDoctor\Migrations;

use ArchitectureDoctor\Rules\Migrations\StagedTableDropRule;
use Tests\TestCase;

class StagedTableDropRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Migrations/StagedDrop';

    public function test_it_ignores_drops_before_introduced_in_and_properly_quarantined_drops(): void
    {
        $rule = new StagedTableDropRule([self::FIXTURES]);

        $findings = $rule->check();

        // Sadece introducedIn (2026-07-23) sonrası VE önceden karantinaya alınmamış
        // 'fixture_gadgets' drop'u işaretlenmeli. 'fixture_widgets_deprecated_20260601'
        // drop'u (düzgün karantina sonrası) ve 2026-07-20 tarihli eski drop işaretlenmemeli.
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('fixture_gadgets', $findings[0]->message);
        $this->assertStringContainsString('drop_gadgets.php', $findings[0]->file);
    }
}
