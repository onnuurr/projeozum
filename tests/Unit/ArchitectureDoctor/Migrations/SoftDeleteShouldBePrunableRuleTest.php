<?php

namespace Tests\Unit\ArchitectureDoctor\Migrations;

use ArchitectureDoctor\Rules\Migrations\SoftDeleteShouldBePrunableRule;
use Tests\TestCase;

class SoftDeleteShouldBePrunableRuleTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/Migrations/Models';

    public function test_it_flags_only_soft_deleted_log_like_models_without_prunable(): void
    {
        $rule = new SoftDeleteShouldBePrunableRule([self::FIXTURES]);

        $findings = $rule->check();

        // FooMovement: SoftDeletes var, Prunable yok, isim deseni eşleşiyor -> flag.
        // BarHistory: hem SoftDeletes hem Prunable var -> flag yok.
        // Product: isim deseni eşleşse bile EXCLUDED_MODELS'te -> flag yok.
        // RandomModel: SoftDeletes var ama isim deseni eşleşmiyor -> flag yok.
        $this->assertCount(1, $findings);
        $this->assertStringContainsString('FooMovement', $findings[0]->message);
    }
}
