<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\GarmentPartAnalyzerContract;

/**
 * Gemini anahtarı yokken / analiz kapalıyken devreye giren passthrough
 * analizci — tüm alanlar confidence=0 ile döner (Rule Engine'de otomatik elenir).
 */
class MockPartAnalyzer implements GarmentPartAnalyzerContract
{
    public function analyze(string $cropImagePath, string $labelKey, string $labelDisplay, string $preservationCategory): array
    {
        $empty = ['value' => null, 'raw_text' => null, 'confidence' => 0.0];

        return [
            'analysis_version' => 1,
            'model'            => 'mock',
            'prompt_version'   => (int) config('creative.garment_detection.analysis.prompt_version', 1),
            'generated_at'     => now()->toIso8601String(),
            'data'             => [
                'instance_priority' => ['value' => null, 'confidence' => 0.0],
                'color'             => $empty,
                'pattern'           => $empty,
                'texture'           => $empty,
                'fabric'            => $empty,
                'stitching'         => $empty,
                'hardware_type'     => $empty,
                'notes'             => null,
            ],
        ];
    }
}
