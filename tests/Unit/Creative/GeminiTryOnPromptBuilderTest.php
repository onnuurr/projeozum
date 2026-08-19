<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder;
use Tests\TestCase;

/**
 * Try-on (giydirme) prompt'unun hem GERÇEK ürün sadakatini (preserve ... EXACTLY)
 * hem de kumaş mühendisliği + kamera + anti-AI gerçekçilik çapalarını taşıdığını
 * doğrular. config() eriştiği için (kamera/toggle) uygulamayı boot eden TestCase.
 */
class GeminiTryOnPromptBuilderTest extends TestCase
{
    private function build(): string
    {
        return strtolower((new GeminiTryOnPromptBuilder())->build());
    }

    public function test_preserves_real_product_exactly(): void
    {
        $prompt = $this->build();

        // Gerçek ürün görseliyle giydirme kararı: ürün uydurulmaz, birebir korunur.
        $this->assertStringContainsString('exactly', $prompt);
        $this->assertStringContainsString('do not redesign', $prompt);
    }

    public function test_includes_fabric_engineering_directives(): void
    {
        $prompt = $this->build();

        $this->assertStringContainsString('ambient occlusion', $prompt);
        $this->assertStringContainsString('micro-texture', $prompt);
    }

    public function test_includes_camera_directive(): void
    {
        $this->assertStringContainsString('hasselblad', $this->build());
    }

    public function test_includes_color_fidelity_negative_constraint(): void
    {
        // Faz Q: rengi İSİMLENDİRMEDEN (ör. "navy blue" demeden) negatif bir kısıt
        // olarak vermeli — isim vermek modelin kendi yorumunu üretmesine yol açar.
        $prompt = $this->build();

        $this->assertStringContainsString('colorimetrically identical', $prompt);
        $this->assertStringContainsString('no color grading', $prompt);
    }

    public function test_photorealistic_word_toggles_with_config(): void
    {
        $this->assertStringContainsString('photorealistic', $this->build());

        config(['creative.ai.prompt.ban_photorealistic_wording' => true]);

        $banned = $this->build();
        $this->assertStringNotContainsString('photorealistic', $banned);
        $this->assertStringContainsString('raw studio photography', $banned);
    }

    public function test_protect_list_sentence_appears_first(): void
    {
        $prompt = (new GeminiTryOnPromptBuilder())->build(
            [],
            null,
            'Critical elements that must NOT be redesigned, replaced, or reinvented: Logo, Pearl buttons.',
        );

        $this->assertStringStartsWith('Critical elements that must NOT be redesigned', $prompt);
    }

    public function test_extra_statement_is_included_when_present(): void
    {
        $prompt = (new GeminiTryOnPromptBuilder())->build([
            ['label' => 'Yaka', 'statement' => "For the 'Yaka' part (HIGH — do not redesign, replace, or invent): color: navy blue. Preserve exactly as shown in the reference image."],
        ]);

        $this->assertStringContainsString('Specific preservation instructions', $prompt);
        $this->assertStringContainsString('navy blue', $prompt);
    }

    public function test_extras_without_statement_behave_like_before(): void
    {
        $prompt = (new GeminiTryOnPromptBuilder())->build([
            ['label' => 'Arkadan'],
        ]);

        $this->assertStringContainsString('"Arkadan" view', $prompt);
        $this->assertStringNotContainsString('Specific preservation instructions', $prompt);
    }
}
