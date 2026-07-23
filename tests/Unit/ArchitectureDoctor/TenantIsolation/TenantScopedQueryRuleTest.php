<?php

namespace Tests\Unit\ArchitectureDoctor\TenantIsolation;

use ArchitectureDoctor\Rules\TenantIsolation\TenantScopedQueryRule;
use Tests\TestCase;

class TenantScopedQueryRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/TenantIsolation';

    public function test_it_flags_bare_all_and_bare_query_get_calls(): void
    {
        $rule = new TenantScopedQueryRule([self::FIXTURES]);

        $findings = $rule->check();

        $this->assertCount(2, $findings);

        $messages = implode(' ', array_map(fn ($f) => $f->message, $findings));
        $this->assertStringContainsString('Product::all()', $messages);
        $this->assertStringContainsString('Category::query()->get()', $messages);

        $files = array_map(fn ($f) => $f->file, $findings);
        $this->assertTrue(collect($files)->every(fn ($f) => str_contains($f, 'UnprotectedController.php')));
    }

    public function test_it_does_not_flag_queries_protected_by_accessible_to_tenant_or_tenant_id_check(): void
    {
        $rule = new TenantScopedQueryRule([self::FIXTURES]);

        $findings = $rule->check();
        $files = array_map(fn ($f) => $f->file, $findings);

        $this->assertFalse(collect($files)->contains(fn ($f) => str_contains($f, 'ProtectedController.php')));
    }
}
