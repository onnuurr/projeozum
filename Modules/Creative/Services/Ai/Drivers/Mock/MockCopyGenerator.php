<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\CopyRequest;

/**
 * Gemini anahtarı yokken devreye giren şablon tabanlı metin sürücüsü.
 *
 * Bilinçli olarak {@see MockPartAnalyzer}'ın confidence=0 desenini DEĞİL,
 * {@see MockCaptionGenerator}'ın deterministik yüksek-confidence (0.9)
 * desenini takip eder — aksi halde mock modda Rule Engine her alanı elerdi
 * ve Gemini anahtarı olmadan uçtan uca doğrulama imkansız olurdu.
 */
class MockCopyGenerator implements CopyGeneratorContract
{
    public function generate(CopyRequest $request): array
    {
        return [
            'copy_version'   => 1,
            'model'          => 'mock',
            'prompt_version' => (int) config('creative.ai.copy.prompt_version', 1),
            'generated_at'   => now()->toIso8601String(),
            'data'           => [
                'headline'     => ['value' => $request->productName . ' şimdi burada', 'confidence' => 0.9],
                'sub_headline' => ['value' => 'Yeni sezon koleksiyonunu keşfet', 'confidence' => 0.9],
                'cta'          => ['value' => $request->ctaPhrases[0] ?? 'Şimdi Keşfet', 'confidence' => 0.9],
            ],
        ];
    }
}
