<?php

namespace Tests\Unit\ArchitectureDoctor\Migrations;

use ArchitectureDoctor\Rules\Migrations\MigrationDownIsRealRule;
use Tests\TestCase;

class MigrationDownIsRealRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Migrations/DownIsReal';

    public function test_it_flags_empty_and_missing_down_but_not_real_down(): void
    {
        $rule = new MigrationDownIsRealRule([self::FIXTURES]);

        $findings = $rule->check();

        $this->assertCount(2, $findings);

        $messages = array_map(fn ($f) => $f->message, $findings);
        $files = array_map(fn ($f) => $f->file, $findings);

        $this->assertStringContainsString('boş/no-op', implode(' ', $messages));
        $this->assertStringContainsString('bir down() metodu tanımlamıyor', implode(' ', $messages));

        $this->assertTrue(collect($files)->contains(fn ($f) => str_contains($f, '000001_empty_down.php')));
        $this->assertTrue(collect($files)->contains(fn ($f) => str_contains($f, '000002_missing_down.php')));
        $this->assertFalse(collect($files)->contains(fn ($f) => str_contains($f, '000000_good_down.php')));
    }

    public function test_it_excludes_whitelisted_migrations_with_intentional_no_op_down(): void
    {
        $rule = new MigrationDownIsRealRule([self::FIXTURES]);

        $findings = $rule->check();

        $files = array_map(fn ($f) => $f->file, $findings);

        $this->assertFalse(collect($files)->contains(
            fn ($f) => str_contains($f, '2026_06_24_110000_create_notifications_table.php')
        ));
    }
}
