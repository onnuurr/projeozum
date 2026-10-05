<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiTryOnPromptBuilder;
use Modules\Creative\Services\Ai\Drivers\Gemini\MannequinPosePromptBuilder;
use Modules\Creative\Services\Ai\MannequinPoseRequest;
use Tests\TestCase;

/**
 * Kombin tamamlama: ürün görseli stil referansı olarak verilince manken poz adımında
 * ürüne uygun bir kombinle (ürünün bölgesine sade yer tutucu) pozlanır; verilmezse
 * eski "sade taban kıyafet korunur" davranışı aynen kalır.
 */
class MannequinPosePromptBuilderStylingTest extends TestCase
{
    private function build(?string $posePreview, ?string $styling): string
    {
        return (new MannequinPosePromptBuilder())->build(new MannequinPoseRequest(
            referenceImagePath: '/tmp/ref.png',
            label: 'Ayakta',
            directive: 'standing upright',
            posePreviewPath: $posePreview,
            stylingGarmentPath: $styling,
        ));
    }

    public function test_without_styling_keeps_plain_base_clothing(): void
    {
        $prompt = $this->build('/tmp/pose.png', null);

        $this->assertStringContainsString('the same plain base clothing', $prompt);
        $this->assertStringNotContainsString('coordinated', $prompt);
    }

    public function test_styling_builds_outfit_around_product_and_skips_product_itself(): void
    {
        $prompt = $this->build('/tmp/pose.png', '/tmp/garment.png');

        $this->assertStringNotContainsString('the same plain base clothing', $prompt);
        $this->assertStringContainsString('Image #3 is a PRODUCT', $prompt);
        $this->assertStringContainsString('do NOT draw the product itself', $prompt);
        $this->assertStringContainsString('complete, coordinated, realistic e-commerce catalog outfit', $prompt);
        $this->assertStringContainsString('placeholder of the SAME garment type', $prompt);
    }

    public function test_styling_image_number_follows_missing_pose_template(): void
    {
        $this->assertStringContainsString('Image #2 is a PRODUCT', $this->build(null, '/tmp/garment.png'));
    }

    public function test_tryon_prompt_replaces_only_product_region(): void
    {
        $prompt = (new GeminiTryOnPromptBuilder())->build();

        $this->assertStringContainsString("Replace ONLY the clothing on the product's own body region", $prompt);
        $this->assertStringContainsString('keep every other piece of the person\'s outfit', $prompt);
    }
}
