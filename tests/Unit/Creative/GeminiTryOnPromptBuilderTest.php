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

    public function test_photorealistic_word_toggles_with_config(): void
    {
        $this->assertStringContainsString('photorealistic', $this->build());

        config(['creative.ai.prompt.ban_photorealistic_wording' => true]);

        $banned = $this->build();
        $this->assertStringNotContainsString('photorealistic', $banned);
        $this->assertStringContainsString('raw studio photography', $banned);
    }
}
