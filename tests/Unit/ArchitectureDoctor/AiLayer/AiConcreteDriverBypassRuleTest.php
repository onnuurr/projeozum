<?php

namespace Tests\Unit\ArchitectureDoctor\AiLayer;

use ArchitectureDoctor\Rules\AiLayer\AiConcreteDriverBypassRule;
use Tests\TestCase;

class AiConcreteDriverBypassRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/AiLayer';

    public function test_it_flags_a_concrete_driver_used_outside_its_provider_and_definition_file(): void
    {
        $rule = new AiConcreteDriverBypassRule(
            providerPaths: [self::FIXTURES.'/Providers'],
            scanPaths: [self::FIXTURES],
        );

        $findings = $rule->check();

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('GeminiBadDriver', $findings[0]->message);
        $this->assertStringContainsString('BypassingConsumer.php', (string) $findings[0]->file);
    }

    public function test_it_does_not_flag_shared_infra_classes_never_bound_to_a_contract(): void
    {
        $rule = new AiConcreteDriverBypassRule(
            providerPaths: [self::FIXTURES.'/Providers'],
            scanPaths: [self::FIXTURES],
        );

        $findings = $rule->check();
        $files = array_map(fn ($f) => $f->file, $findings);

        // GeminiSharedClient (fixture'daki GeminiClient karşılığı) hiçbir Contract'a bind
        // edilmediği için LegitimateConsumer'ın onu inject etmesi bypass sayılmamalı.
        $this->assertFalse(collect($files)->contains(fn ($f) => str_contains($f, 'LegitimateConsumer.php')));
    }

    public function test_it_does_not_flag_the_drivers_own_definition_file_or_provider(): void
    {
        $rule = new AiConcreteDriverBypassRule(
            providerPaths: [self::FIXTURES.'/Providers'],
            scanPaths: [self::FIXTURES],
        );

        $findings = $rule->check();
        $files = array_map(fn ($f) => $f->file, $findings);

        $this->assertFalse(collect($files)->contains(fn ($f) => str_contains($f, 'GeminiBadDriver.php')));
        $this->assertFalse(collect($files)->contains(fn ($f) => str_contains($f, 'BadServiceProvider.php')));
    }
}
