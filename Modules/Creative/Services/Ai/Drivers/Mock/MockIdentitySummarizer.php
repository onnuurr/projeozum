<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\GarmentIdentitySummarizerContract;

/**
 * Gemini anahtarı yokken / özellik kapalıyken devreye giren passthrough —
 * boş highlights listesi döner.
 */
class MockIdentitySummarizer implements GarmentIdentitySummarizerContract
{
    public function summarize(string $garmentImagePath): array
    {
        return [
            'analysis_version' => 1,
            'model'            => 'mock',
            'prompt_version'   => (int) config('creative.garment_detection.analysis.prompt_version', 1),
            'generated_at'     => now()->toIso8601String(),
            'highlights'       => [],
        ];
    }
}
