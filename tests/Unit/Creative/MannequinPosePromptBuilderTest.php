<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder;
use Modules\Creative\Services\Ai\Drivers\Gemini\MannequinPosePromptBuilder;
use Modules\Creative\Services\Ai\MannequinPoseRequest;
use Tests\TestCase;

/**
 * Eski giydirme hattı (mokap dışı kategoriler): manken poz adımında sade taban kıyafetini korur.
 * "Kombin tamamlama" (creative.ai.tryon_styling) 2026-10-06'da kaldırıldı — kombinli katalog
 * çekimi mokap hattında (kilitli baz görsel).
 */
class MannequinPosePromptBuilderTest extends TestCase
{
    public function test_pose_keeps_plain_base_clothing_and_never_styles_an_outfit(): void
    {
        $prompt = (new MannequinPosePromptBuilder())->build(new MannequinPoseRequest(
            referenceImagePath: '/tmp/ref.png',
            label: 'Ayakta',
            directive: 'standing upright',
            posePreviewPath: '/tmp/pose.png',
        ));

        $this->assertStringContainsString('the same plain base clothing', $prompt);
        $this->assertStringNotContainsString('coordinated', $prompt);
        $this->assertStringNotContainsString('is a PRODUCT', $prompt);
    }

    public function test_tryon_prompt_replaces_only_product_region(): void
    {
        $prompt = (new GeminiTryOnPromptBuilder())->build();

        $this->assertStringContainsString("Replace ONLY the clothing on the product's own body region", $prompt);
        $this->assertStringContainsString('keep every other piece of the person\'s outfit', $prompt);
    }
}
