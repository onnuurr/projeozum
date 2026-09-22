<?php

namespace Modules\PatternEngine\Services\AiExtraction\Drivers\Mock;

use Modules\PatternEngine\Services\AiExtraction\Contracts\PatternMetadataExtractorContract;

/**
 * Gemini anahtarı yokken / driver=mock iken devreye giren passthrough
 * çıkarıcı — tüm alanlar confidence=0 ile döner (Rule Engine'de otomatik elenir).
 */
class MockPatternMetadataExtractor implements PatternMetadataExtractorContract
{
    public function extract(string $coverImagePath): array
    {
        return [
            'analysis_version' => 1,
            'model'            => 'mock',
            'prompt_version'   => (int) config('patternengine.pattern_metadata_ai.prompt_version', 1),
            'generated_at'     => now()->toIso8601String(),
            'data'             => [
                'product_type' => ['value' => null, 'raw_text' => null, 'confidence' => 0.0],
                'size_range'   => ['value' => null, 'confidence' => 0.0],
            ],
        ];
    }
}
