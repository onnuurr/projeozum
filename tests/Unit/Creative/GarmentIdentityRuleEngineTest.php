<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\GarmentIdentityRuleEngine;
use Tests\TestCase;

/**
 * Saf iş kuralı katmanının (bkz. ROADMAP.md Faz G.5) doğru çalıştığını
 * doğrular: confidence eşiği altındaki alanlar elenir, nihai öncelik
 * etiketin taban değerinin ALTINA asla düşmez, eski usul (label_key'siz)
 * extralar davranışsız (statement=null) kalır. Hiçbir AI/DB çağrısı yapmaz.
 */
class GarmentIdentityRuleEngineTest extends TestCase
{
    private function engine(): GarmentIdentityRuleEngine
    {
        config(['creative.garment_detection.analysis.confidence_threshold' => 0.6]);

        return new GarmentIdentityRuleEngine();
    }

    private function analysisData(array $overrides = []): array
    {
        $empty = ['value' => null, 'raw_text' => null, 'confidence' => 0.0];

        return array_merge([
            'instance_priority' => ['value' => null, 'confidence' => 0.0],
            'color'             => $empty,
            'pattern'           => $empty,
            'texture'           => $empty,
            'fabric'            => $empty,
            'stitching'         => $empty,
            'hardware_type'     => $empty,
            'notes'             => null,
        ], $overrides);
    }

    public function test_low_confidence_fields_are_excluded_from_statement(): void
    {
        // fabric %42 güvenle geliyor (eşik %60) VE öncelik medium (protect=false) —
        // ne söylenecek güvenilir bir şey var ne kritik bir koruma gerekçesi,
        // bu yüzden statement bilinçli olarak null kalır (asla düşük güvenli bir
        // tahmini "gerçek" diye prompt'a yazmaz).
        $extra = [
            'path' => '/tmp/a.png', 'label' => 'Kumaş',
            'label_key' => 'kumas_dokusu', 'default_priority' => 'medium',
            'analysis' => ['data' => $this->analysisData([
                'fabric' => ['value' => 'COTTON', 'raw_text' => null, 'confidence' => 0.42],
            ])],
        ];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertNull($directive['statement']);
    }

    public function test_low_confidence_field_dropped_but_statement_kept_when_protected(): void
    {
        // Aynı düşük güvenli fabric alanı, ama öncelik critical (protect=true) —
        // statement YİNE oluşur (kritik parça olduğu söylenir) ama fabric değeri
        // metne YAZILMAZ (güvenilir değil).
        $extra = [
            'path' => '/tmp/a.png', 'label' => 'Düğme',
            'label_key' => 'dugme', 'default_priority' => 'critical',
            'analysis' => ['data' => $this->analysisData([
                'fabric' => ['value' => 'COTTON', 'raw_text' => null, 'confidence' => 0.42],
            ])],
        ];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertNotNull($directive['statement']);
        $this->assertStringNotContainsString('cotton', $directive['statement']);
        $this->assertStringContainsString('do not redesign', $directive['statement']);
    }

    public function test_confident_fields_are_included_in_statement(): void
    {
        $extra = [
            'path' => '/tmp/a.png', 'label' => 'Düğme',
            'label_key' => 'dugme', 'default_priority' => 'critical',
            'analysis' => ['data' => $this->analysisData([
                'hardware_type' => ['value' => 'PEARL_BUTTON', 'raw_text' => null, 'confidence' => 0.9],
            ])],
        ];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertStringContainsString('pearl button', $directive['statement']);
        $this->assertStringContainsString('do not redesign', $directive['statement']);
    }

    public function test_priority_never_drops_below_label_default(): void
    {
        // Gemini bu örneği "low" görse bile, etiketin (düğme) taban değeri
        // critical olduğu için nihai öncelik critical KALIR.
        $extra = [
            'path' => '/tmp/a.png', 'label' => 'Düğme',
            'label_key' => 'dugme', 'default_priority' => 'critical',
            'analysis' => ['data' => $this->analysisData([
                'instance_priority' => ['value' => 'low', 'confidence' => 0.95],
            ])],
        ];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertSame('critical', $directive['priority']);
        $this->assertTrue($directive['protect']);
    }

    public function test_instance_priority_can_raise_above_default(): void
    {
        $extra = [
            'path' => '/tmp/a.png', 'label' => 'Baskı',
            'label_key' => 'baski_desen', 'default_priority' => 'medium',
            'analysis' => ['data' => $this->analysisData([
                'instance_priority' => ['value' => 'critical', 'confidence' => 0.9],
            ])],
        ];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertSame('critical', $directive['priority']);
    }

    public function test_low_confidence_instance_priority_is_ignored(): void
    {
        $extra = [
            'path' => '/tmp/a.png', 'label' => 'Kumaş',
            'label_key' => 'kumas_dokusu', 'default_priority' => 'medium',
            'analysis' => ['data' => $this->analysisData([
                'instance_priority' => ['value' => 'critical', 'confidence' => 0.3],
            ])],
        ];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertSame('medium', $directive['priority']);
    }

    public function test_legacy_extra_without_label_key_is_untouched(): void
    {
        $extra = ['path' => '/tmp/a.png', 'label' => 'Arkadan'];

        $directive = $this->engine()->directivesFor([$extra])[0];

        $this->assertNull($directive['statement']);
        $this->assertNull($directive['priority']);
        $this->assertFalse($directive['protect']);
    }

    public function test_protect_list_sentence_names_only_critical_and_high(): void
    {
        $directives = [
            ['label' => 'Logo', 'priority' => 'critical', 'protect' => true],
            ['label' => 'Yaka', 'priority' => 'high', 'protect' => true],
            ['label' => 'Kumaş', 'priority' => 'medium', 'protect' => false],
        ];

        $sentence = $this->engine()->protectListSentence($directives);

        $this->assertStringContainsString('Logo', $sentence);
        $this->assertStringContainsString('Yaka', $sentence);
        $this->assertStringNotContainsString('Kumaş', $sentence);
    }

    public function test_protect_list_sentence_is_null_when_nothing_critical(): void
    {
        $directives = [
            ['label' => 'Kumaş', 'priority' => 'medium', 'protect' => false],
        ];

        $this->assertNull($this->engine()->protectListSentence($directives));
    }
}
