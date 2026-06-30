<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Ai\Drivers\Gemini\MannequinPromptBuilder;
use Modules\Creative\Services\Ai\MannequinRequest;
use PHPUnit\Framework\TestCase;

/**
 * Sanal manken kimlik prompt'unun cinsiyet/yaş/karakter yönlendirmelerini
 * GÜÇLÜ biçimde (özne ismine gömerek) ürettiğini ve gerçekçilik çapaları
 * taşıdığını doğrular. Saf birim test — DB gerektirmez.
 */
class MannequinPromptBuilderTest extends TestCase
{
    private function build(MannequinRequest $request): string
    {
        return (new MannequinPromptBuilder())->build($request);
    }

    public function test_prompt_override_passes_through(): void
    {
        $r = new MannequinRequest(name: 'X', promptOverride: 'CUSTOM PROMPT');

        $this->assertSame('CUSTOM PROMPT', $this->build($r));
    }

    public function test_adult_female_is_named_as_woman_in_subject(): void
    {
        $r = new MannequinRequest(name: 'X', gender: 'female', ageRange: '25-35');
        $prompt = strtolower($this->build($r));

        // Cinsiyet, zayıf kuyruk-sıfatı değil; özne isminde net olmalı.
        $this->assertStringContainsString('woman', $prompt);
        $this->assertStringNotContainsString('one person modeling', $prompt);
    }

    public function test_adult_male_is_named_as_man(): void
    {
        $r = new MannequinRequest(name: 'X', gender: 'male', ageRange: '25-35');
        $prompt = strtolower($this->build($r));

        $this->assertStringContainsString('man', $prompt);
    }

    public function test_female_child_becomes_girl_without_adult_measurements(): void
    {
        $r = new MannequinRequest(
            name: 'X', gender: 'female', ageRange: '6-9',
            heightCm: 130, bustCm: 70, waistCm: 60, hipsCm: 72,
        );
        $prompt = strtolower($this->build($r));

        $this->assertStringContainsString('girl', $prompt);
        // Çocukta yetişkin göğüs/bel/kalça ölçüleri prompt'a girmemeli.
        $this->assertStringNotContainsString('bust', $prompt);
        $this->assertStringNotContainsString('hips', $prompt);
        // Boy bilgisi korunabilir.
        $this->assertStringContainsString('130', $prompt);
    }

    public function test_male_child_becomes_boy(): void
    {
        $r = new MannequinRequest(name: 'X', gender: 'male', ageRange: '3-5');
        $prompt = strtolower($this->build($r));

        $this->assertStringContainsString('boy', $prompt);
    }

    public function test_unisex_does_not_emit_meaningless_phrase(): void
    {
        $r = new MannequinRequest(name: 'X', gender: 'unisex', ageRange: '25-35');
        $prompt = strtolower($this->build($r));

        $this->assertStringNotContainsString('is unisex', $prompt);
        $this->assertStringNotContainsString(', unisex,', $prompt);
    }

    public function test_realism_anchors_present(): void
    {
        $r = new MannequinRequest(name: 'X', gender: 'female', ageRange: '25-35');
        $prompt = strtolower($this->build($r));

        $this->assertStringContainsString('real living person', $prompt);
        $this->assertStringContainsString('not a 3d render', $prompt);
    }

    public function test_character_directives_are_included(): void
    {
        $r = new MannequinRequest(
            name: 'X', gender: 'female', ageRange: '25-35',
            skinTone: 'medium', bodyType: 'athletic',
            hair: 'long straight brown', face: 'oval face, brown eyes',
        );
        $prompt = strtolower($this->build($r));

        $this->assertStringContainsString('athletic', $prompt);
        $this->assertStringContainsString('medium skin tone', $prompt);
        $this->assertStringContainsString('long straight brown', $prompt);
        $this->assertStringContainsString('oval face, brown eyes', $prompt);
    }
}
