<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\LayoutConstraintEngine;
use Tests\TestCase;

/**
 * Saf iş kuralı katmanının doğru çalıştığını doğrular: headline/CTA kelime
 * sayısı tavanı, OCR-metin eşleşmesi, safe-margin kontrolü. Hiçbir AI/OCR/DB
 * çağrısı yapmaz — OCR sonucu her zaman test verisi olarak verilir.
 */
class LayoutConstraintEngineTest extends TestCase
{
    private function engine(): LayoutConstraintEngine
    {
        config([
            'creative.composition.constraints.headline_max_words'        => 3,
            'creative.composition.constraints.headline_max_lines'        => 1,
            'creative.composition.constraints.cta_max_words'              => 3,
            'creative.composition.constraints.safe_margin_pct'            => 0.06,
            'creative.composition.constraints.text_match_min_similarity' => 0.85,
            'creative.constraints.layout.hero_coverage_min_pct' => 0.30,
            'creative.constraints.layout.whitespace_min_pct'    => 0.08,
            'creative.constraints.layout.require_logo_slot'     => false,
            'creative.constraints.layout.overlap_tolerance_px'  => 4,
        ]);

        return new LayoutConstraintEngine();
    }

    /** TemplateGeneratorService::generate('product_full_bleed', 'instagram_post') gerçek çıktısı. */
    private function fullBleedSlots(): array
    {
        return [
            ['key' => 'product_image', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 1080, 'fit' => 'cover'],
            ['key' => 'logo', 'type' => 'image', 'x' => 928.8, 'y' => 43.2, 'w' => 108, 'h' => 108, 'fit' => 'contain'],
            ['key' => 'headline', 'type' => 'text', 'x' => 64.8, 'y' => 842.4, 'font_size' => 56.16, 'align' => 'left', 'fill' => 'token:background', 'bold' => true, 'w' => 933.98],
            ['key' => 'sub_headline', 'type' => 'text', 'x' => 64.8, 'y' => 923.4, 'font_size' => 32.4, 'align' => 'left', 'fill' => 'token:background', 'bold' => false, 'w' => 933.98],
            ['key' => 'cta_button', 'type' => 'text', 'x' => 108, 'y' => 1013.04, 'font_size' => 28.08, 'align' => 'left', 'fill' => 'token:background', 'bold' => true, 'w' => 894.24],
        ];
    }

    /** TemplateGeneratorService::generate('product_bottom_banner', 'instagram_story') gerçek çıktısı. */
    private function bottomBannerSlots(): array
    {
        return [
            ['key' => 'product_image', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 1080, 'h' => 1344, 'fit' => 'cover'],
            ['key' => 'logo', 'type' => 'image', 'x' => 64.8, 'y' => 1411.2, 'w' => 97.2, 'h' => 172.8, 'fit' => 'contain'],
            ['key' => 'headline', 'type' => 'text', 'x' => 64.8, 'y' => 1680, 'font_size' => 88.32, 'align' => 'left', 'fill' => 'token:text', 'bold' => true, 'w' => 933.98],
            ['key' => 'sub_headline', 'type' => 'text', 'x' => 64.8, 'y' => 1795.2, 'font_size' => 53.76, 'align' => 'left', 'fill' => 'token:secondary', 'bold' => false, 'w' => 933.98],
            ['key' => 'cta_button', 'type' => 'text', 'x' => 777.6, 'y' => 1622.4, 'font_size' => 49.92, 'align' => 'left', 'fill' => 'token:background', 'bold' => true, 'w' => 278.21],
        ];
    }

    private function format(): array
    {
        return ['width' => 1080, 'height' => 1350];
    }

    private function word(string $text, int $x, int $y, int $w = 100, int $h = 40, float $confidence = 0.9): array
    {
        return ['text' => $text, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'confidence' => $confidence];
    }

    public function test_headline_within_word_limit_passes_precheck(): void
    {
        $out = $this->engine()->checkIntendedText(['headline' => 'ESINTI']);

        $this->assertTrue($out['passed']);
        $this->assertSame([], $out['violations']);
    }

    public function test_headline_exceeding_word_limit_fails_precheck(): void
    {
        $out = $this->engine()->checkIntendedText(['headline' => 'Yeni Sezon Rüzgarı Burada Şimdi']);

        $this->assertFalse($out['passed']);
        $this->assertNotEmpty($out['violations']);
    }

    public function test_cta_exceeding_word_limit_fails_precheck(): void
    {
        $out = $this->engine()->checkIntendedText(['cta_button' => 'Hemen Şimdi Sepete Ekle Kaçırma']);

        $this->assertFalse($out['passed']);
    }

    public function test_matching_headline_within_safe_margin_passes_evaluation(): void
    {
        $ocr = [$this->word('ESINTI', 400, 100, 280, 80)];

        $out = $this->engine()->evaluate($ocr, ['headline' => 'ESINTI'], $this->format(), false);

        $this->assertTrue($out['passed']);
    }

    public function test_missing_headline_text_fails_evaluation(): void
    {
        $ocr = [$this->word('SOMETHING', 400, 100, 280, 80)];

        $out = $this->engine()->evaluate($ocr, ['headline' => 'ESINTI'], $this->format(), false);

        $this->assertFalse($out['passed']);
        $this->assertNotEmpty($out['violations']);
    }

    public function test_headline_outside_safe_margin_fails_evaluation(): void
    {
        // Sol üst köşeye çok yakın (safe_margin_pct=0.06 -> ~65px marj) — dışarıda.
        $ocr = [$this->word('ESINTI', 0, 0, 100, 30)];

        $out = $this->engine()->evaluate($ocr, ['headline' => 'ESINTI'], $this->format(), false);

        $this->assertFalse($out['passed']);
    }

    public function test_required_cta_missing_fails_evaluation(): void
    {
        $ocr = [$this->word('ESINTI', 400, 100, 280, 80)];

        $out = $this->engine()->evaluate($ocr, ['headline' => 'ESINTI', 'cta_button' => null], $this->format(), true);

        $this->assertFalse($out['passed']);
    }

    public function test_matching_headline_and_cta_pass_evaluation(): void
    {
        $ocr = [
            $this->word('ESINTI', 400, 100, 280, 80),
            $this->word('Şimdi', 400, 1200, 120, 50),
            $this->word('Keşfet', 540, 1200, 140, 50),
        ];

        $out = $this->engine()->evaluate(
            $ocr,
            ['headline' => 'ESINTI', 'cta_button' => 'Şimdi Keşfet'],
            $this->format(),
            true,
        );

        $this->assertTrue($out['passed']);
    }

    public function test_empty_ocr_result_fails_evaluation(): void
    {
        $out = $this->engine()->evaluate([], ['headline' => 'ESINTI'], $this->format(), false);

        $this->assertFalse($out['passed']);
    }

    // ── checkSlotGeometry (Faz N) ───────────────────────────────────────────

    public function test_generated_full_bleed_preset_passes_geometry_check(): void
    {
        $out = $this->engine()->checkSlotGeometry($this->fullBleedSlots(), 1080, 1080);

        $this->assertTrue($out['passed'], implode('; ', $out['violations']));
    }

    public function test_generated_bottom_banner_preset_passes_geometry_check(): void
    {
        $out = $this->engine()->checkSlotGeometry($this->bottomBannerSlots(), 1080, 1920);

        $this->assertTrue($out['passed'], implode('; ', $out['violations']));
    }

    public function test_duplicate_slot_key_fails_geometry_check(): void
    {
        $slots = $this->fullBleedSlots();
        $slots[] = ['key' => 'logo', 'type' => 'image', 'x' => 10, 'y' => 10, 'w' => 40, 'h' => 40];

        $out = $this->engine()->checkSlotGeometry($slots, 1080, 1080);

        $this->assertFalse($out['passed']);
        $this->assertStringContainsString('logo', implode(' ', $out['violations']));
    }

    public function test_tiny_hero_image_fails_coverage_check(): void
    {
        $slots = [
            ['key' => 'product_image', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100],
        ];

        $out = $this->engine()->checkSlotGeometry($slots, 1080, 1080);

        $this->assertFalse($out['passed']);
    }

    public function test_overlapping_slots_fail_geometry_check(): void
    {
        $slots = [
            ['key' => 'logo', 'type' => 'image', 'x' => 100, 'y' => 100, 'w' => 200, 'h' => 200],
            ['key' => 'headline', 'type' => 'image', 'x' => 120, 'y' => 120, 'w' => 200, 'h' => 200],
        ];

        $out = $this->engine()->checkSlotGeometry($slots, 1080, 1080);

        $this->assertFalse($out['passed']);
    }

    public function test_empty_slots_passes_geometry_check(): void
    {
        $out = $this->engine()->checkSlotGeometry([], 1080, 1080);

        $this->assertTrue($out['passed']);
        $this->assertSame(100.0, $out['metrics']['whitespace_pct']);
    }
}
