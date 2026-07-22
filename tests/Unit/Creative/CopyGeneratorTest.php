<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\Ai\CopyRequest;
use Modules\Creative\Services\Ai\Drivers\Gemini\CopyPromptBuilder;
use Modules\Creative\Services\Ai\Drivers\Mock\MockCopyGenerator;
use Modules\Creative\Services\Ai\Support\CopyConstraints;
use Modules\Creative\Services\Ai\Support\CopySlots;
use Tests\TestCase;

/**
 * On-image copy üretiminin saf/DB'siz garantilerini doğrular:
 *  - slot anahtarı → semantik rol çözümü (subheadline, headline karışmaz),
 *  - yasaklı kelime KOD seviyesinde ayıklanır (LLM yok sayarsa bile),
 *  - prompt marka kısıtlarını (ban listesi, CTA, ek talimat) taşır,
 *  - mock sürücü rollere uygun, ban-güvenli metin üretir.
 */
class CopyGeneratorTest extends TestCase
{
    // ---- CopySlots: rol çözümü ------------------------------------------------

    public function test_role_resolution_distinguishes_headline_and_subheadline(): void
    {
        $this->assertSame('headline', CopySlots::roleFor('headline'));
        $this->assertSame('headline', CopySlots::roleFor('hero_title'));
        // "subheadline"/"altbaslik" alt-dizeleri headline'a KAYMAMALI.
        $this->assertSame('subheadline', CopySlots::roleFor('subheadline'));
        $this->assertSame('subheadline', CopySlots::roleFor('subtitle'));
        $this->assertSame('subheadline', CopySlots::roleFor('altbaslik'));
        $this->assertSame('cta', CopySlots::roleFor('cta_button'));
    }

    public function test_product_name_and_unknown_keys_are_not_copy_keys(): void
    {
        $this->assertNull(CopySlots::roleFor('product_name'));
        $this->assertFalse(CopySlots::isCopyKey('product_name'));
        $this->assertNull(CopySlots::roleFor('price'));
        $this->assertFalse(CopySlots::isCopyKey('price'));
        $this->assertTrue(CopySlots::isCopyKey('cta'));
    }

    // ---- CopyConstraints: yasaklı kelime güvencesi ----------------------------

    public function test_banned_words_are_stripped_whole_word_case_insensitive(): void
    {
        $out = CopyConstraints::sanitize(
            ['headline' => 'Ucuz ve UCUZ fırsat', 'cta' => 'Hemen keşfet'],
            ['ucuz'],
        );

        $this->assertArrayHasKey('cta', $out);
        // "ucuz" (her iki büyük/küçük hâli) düşmeli; slot boşalırsa tamamen atılır.
        if (isset($out['headline'])) {
            $this->assertStringNotContainsStringIgnoringCase('ucuz', $out['headline']);
        }
        $this->assertSame('Hemen keşfet', $out['cta']);
    }

    public function test_banned_word_does_not_match_inside_other_word(): void
    {
        // "art" yasaklıysa "sanat" içindeki "art" eşleşmemeli (tam kelime sınırı).
        $out = CopyConstraints::sanitize(['headline' => 'Sanat dolu koleksiyon'], ['art']);

        $this->assertSame('Sanat dolu koleksiyon', $out['headline']);
    }

    public function test_empty_after_strip_drops_the_slot(): void
    {
        $out = CopyConstraints::sanitize(['headline' => 'indirim'], ['indirim']);

        $this->assertArrayNotHasKey('headline', $out);
    }

    // ---- CopyPromptBuilder: marka kısıtları prompt'ta -------------------------

    public function test_prompt_carries_brand_constraints_and_extra_instruction(): void
    {
        $prompt = (new CopyPromptBuilder())->build(new CopyRequest(
            productName: 'Keten Gömlek',
            category: 'Gömlek',
            slotKeys: ['headline', 'cta'],
            brief: 'Sade ve doğal marka dili',
            tone: 'sıcak',
            ctaPhrases: ['Hemen keşfet'],
            bannedWords: ['ucuz'],
            aspectLabel: '9:16 vertical',
            extraInstructions: 'logoyu vurgula',
        ));

        $this->assertStringContainsString('Keten Gömlek', $prompt);
        $this->assertStringContainsString('ucuz', $prompt);          // ban listesi
        $this->assertStringContainsString('Hemen keşfet', $prompt);  // CTA ipucu
        $this->assertStringContainsString('Sade ve doğal marka dili', $prompt);
        $this->assertStringContainsString('logoyu vurgula', $prompt); // ek talimat brief'e ek
        $this->assertStringContainsString('"headline"', $prompt);
        $this->assertStringContainsString('"cta"', $prompt);
    }

    // ---- MockCopyGenerator: rol uyumu + ban güvenliği ------------------------

    public function test_mock_generates_role_appropriate_ban_safe_copy(): void
    {
        $out = (new MockCopyGenerator())->generate(new CopyRequest(
            productName: 'Keten Gömlek',
            slotKeys: ['headline', 'subheadline', 'cta'],
            ctaPhrases: ['Sepete ekle'],
            bannedWords: ['gömlek'],
        ));

        $this->assertArrayHasKey('subheadline', $out);
        $this->assertSame('Sepete ekle', $out['cta']);   // markanın CTA ifadesi
        // headline ürün adıydı ama "gömlek" yasaklı → ayıklanınca "Keten" kalır.
        $this->assertFalse(CopyConstraints::containsBanned(implode(' ', $out), ['gömlek']));
    }

    public function test_mock_returns_empty_when_no_copy_slots(): void
    {
        $out = (new MockCopyGenerator())->generate(new CopyRequest(
            productName: 'Keten Gömlek',
            slotKeys: [],
        ));

        $this->assertSame([], $out);
    }
}
