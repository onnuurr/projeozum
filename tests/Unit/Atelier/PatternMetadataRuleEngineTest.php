<?php

namespace Tests\Unit\Atelier;

use Modules\Atelier\Services\PatternMetadataRuleEngine;
use Tests\TestCase;

class PatternMetadataRuleEngineTest extends TestCase
{
    private function envelope(array $data): array
    {
        return ['data' => $data];
    }

    public function test_below_threshold_is_discarded(): void
    {
        $out = (new PatternMetadataRuleEngine())->decide($this->envelope([
            'product_type' => ['value' => 'tulum', 'raw_text' => null, 'confidence' => 0.3],
            'size_range' => ['value' => '68-128', 'confidence' => 0.3],
        ]));

        $this->assertNull($out['product_type']);
        $this->assertNull($out['size_range']);
    }

    public function test_confident_enum_value_passes_through(): void
    {
        $out = (new PatternMetadataRuleEngine())->decide($this->envelope([
            'product_type' => ['value' => 'tulum', 'raw_text' => null, 'confidence' => 0.9],
            'size_range' => ['value' => '68-128', 'confidence' => 0.9],
        ]));

        $this->assertSame('tulum', $out['product_type']);
        $this->assertSame('68-128', $out['size_range']);
    }

    public function test_other_with_short_raw_text_resolves(): void
    {
        $out = (new PatternMetadataRuleEngine())->decide($this->envelope([
            'product_type' => ['value' => 'OTHER', 'raw_text' => 'denim ceket', 'confidence' => 0.8],
            'size_range' => ['value' => null, 'confidence' => 0.0],
        ]));

        $this->assertSame('denim ceket', $out['product_type']);
    }

    /**
     * Gerçek vaka: kapak sayfasında ürün fotoğrafı olmayan bir kalıp karosunda
     * Gemini "OTHER" + raw_text'e ürün adı yerine YÜKSEK-confidence'lı bir
     * gerekçe CÜMLESİ yazdı ("The image shows a single pattern piece...
     * cannot be determined from this page alone."). Bu, filtrelenmeden
     * product_type kolonuna yazılıyordu — artık uzunluk sınırı üstündeki
     * raw_text "belirlenemedi" (null) sayılır.
     */
    public function test_other_with_long_sentence_raw_text_is_discarded(): void
    {
        $out = (new PatternMetadataRuleEngine())->decide($this->envelope([
            'product_type' => [
                'value' => 'OTHER',
                'raw_text' => "The image shows a single pattern piece labeled 'back half'. "
                    . 'Without a product photo or further descriptive text, the specific '
                    . 'garment type cannot be determined from this page alone.',
                'confidence' => 0.9,
            ],
            'size_range' => ['value' => null, 'confidence' => 1.0],
        ]));

        $this->assertNull($out['product_type']);
    }

    public function test_missing_field_is_null(): void
    {
        $out = (new PatternMetadataRuleEngine())->decide(['data' => []]);

        $this->assertNull($out['product_type']);
        $this->assertNull($out['size_range']);
    }
}
