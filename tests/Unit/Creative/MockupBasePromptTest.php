<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Ai\Drivers\Gemini\MockupPromptBuilder;
use Tests\TestCase;

/**
 * Mokap bazları canlı bir çekim gibi: doğal duruş, poz şablonu kaskatı birebir kopyalanmaz; yüz ifadesi
 * kareden kareye değişir (her karede aynı gülümseme yapay duruyordu).
 */
class MockupBasePromptTest extends TestCase
{
    public function test_bases_ask_for_a_relaxed_model_not_a_rigid_template_copy(): void
    {
        $b = new MockupPromptBuilder();
        foreach ([$b->masterBase('Outfit: x.'), $b->derivedBase('Outfit: x.', 'laugh')] as $prompt) {
            $this->assertStringContainsString('NOT a stiff store mannequin', $prompt);
            $this->assertStringNotContainsString('Reproduce its body pose EXACTLY', $prompt);
            $this->assertStringContainsString('blank expression', $prompt);
        }
    }

    public function test_each_frame_gets_its_own_expression(): void
    {
        $b = new MockupPromptBuilder();

        $this->assertStringContainsString(MockupPromptBuilder::EXPRESSIONS[MockupPromptBuilder::MASTER_EXPRESSION], $b->masterBase('Outfit: x.'));
        $laugh = $b->derivedBase('Outfit: x.', 'laugh');
        $this->assertStringContainsString(MockupPromptBuilder::EXPRESSIONS['laugh'], $laugh);
        $this->assertStringContainsString("do NOT copy the master photo's expression", $laugh);
        $this->assertStringNotContainsString(MockupPromptBuilder::EXPRESSIONS['soft_smile'], $laugh);
    }
}
