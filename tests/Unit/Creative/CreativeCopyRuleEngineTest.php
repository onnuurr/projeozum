<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\CreativeCopyRuleEngine;
use Tests\TestCase;

/**
 * Saf iş kuralı katmanının doğru çalıştığını doğrular: confidence eşiği
 * altındaki alanlar elenir, CTA kapalı listeye göre normalize/coerce edilir,
 * yasaklı kelime geçen alan tamamen düşer, slot genişliğini aşan metin
 * kırpılır. Hiçbir AI/DB çağrısı yapmaz.
 */
class CreativeCopyRuleEngineTest extends TestCase
{
    private function engine(): CreativeCopyRuleEngine
    {
        config([
            'creative.ai.copy.confidence_threshold' => 0.6,
            'creative.ai.copy.glyph_width_factor'    => 0.55,
        ]);

        return new CreativeCopyRuleEngine();
    }

    private function data(array $overrides = []): array
    {
        $empty = ['value' => null, 'confidence' => 0.0];

        return array_merge([
            'headline'     => $empty,
            'sub_headline' => $empty,
            'cta'          => $empty,
        ], $overrides);
    }

    private function slots(array $overrides = []): array
    {
        return array_merge([
            'headline'     => [],
            'sub_headline' => [],
            'cta_button'   => [],
        ], $overrides);
    }

    public function test_low_confidence_field_is_excluded(): void
    {
        $data = $this->data([
            'headline' => ['value' => 'Yeni Sezon', 'confidence' => 0.42],
        ]);

        $out = $this->engine()->resolve($data, [], $this->slots());

        $this->assertNull($out['headline']);
    }

    public function test_confident_field_is_included(): void
    {
        $data = $this->data([
            'headline' => ['value' => 'Yeni Sezon', 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, [], $this->slots());

        $this->assertSame('Yeni Sezon', $out['headline']);
    }

    public function test_cta_is_normalized_to_whitelist_canonical_spelling(): void
    {
        $data = $this->data([
            'cta' => ['value' => 'şimdi keşfet', 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, ['cta_phrases' => ['Şimdi Keşfet', 'Sepete Ekle']], $this->slots());

        $this->assertSame('Şimdi Keşfet', $out['cta_button']);
    }

    public function test_cta_outside_whitelist_is_coerced_to_first_approved_phrase(): void
    {
        $data = $this->data([
            'cta' => ['value' => 'Hemen Al', 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, ['cta_phrases' => ['Şimdi Keşfet', 'Sepete Ekle']], $this->slots());

        $this->assertSame('Şimdi Keşfet', $out['cta_button']);
    }

    public function test_raw_cta_value_kept_when_whitelist_is_empty(): void
    {
        $data = $this->data([
            'cta' => ['value' => 'Hemen Al', 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, ['cta_phrases' => []], $this->slots());

        $this->assertSame('Hemen Al', $out['cta_button']);
    }

    public function test_banned_word_drops_the_entire_field(): void
    {
        $data = $this->data([
            'headline' => ['value' => 'Ücretsiz Kargo Garantisi Burada', 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, ['banned_words' => ['ücretsiz kargo garantisi']], $this->slots());

        $this->assertNull($out['headline']);
    }

    public function test_text_exceeding_slot_width_is_truncated(): void
    {
        $data = $this->data([
            'headline' => ['value' => 'Bu başlık gerçekten oldukça uzun bir cümle', 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, [], $this->slots([
            'headline' => ['w' => 200, 'font_size' => 32],
        ]));

        $this->assertStringEndsWith('…', $out['headline']);
        $this->assertLessThan(mb_strlen('Bu başlık gerçekten oldukça uzun bir cümle'), mb_strlen($out['headline']));
    }

    public function test_width_cap_is_skipped_when_slot_geometry_is_missing(): void
    {
        $long = 'Bu başlık gerçekten oldukça uzun bir cümle ve hiç kırpılmamalı';
        $data = $this->data([
            'headline' => ['value' => $long, 'confidence' => 0.9],
        ]);

        $out = $this->engine()->resolve($data, [], $this->slots());

        $this->assertSame($long, $out['headline']);
    }

    public function test_empty_ai_data_returns_null_triplet_without_error(): void
    {
        $out = $this->engine()->resolve([], [], $this->slots());

        $this->assertSame(['headline' => null, 'sub_headline' => null, 'cta_button' => null], $out);
    }
}
