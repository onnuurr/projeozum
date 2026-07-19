<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Contracts\GarmentIdentitySummarizerContract;
use Throwable;

/**
 * Gemini Vision ile bütünsel "ayırt edici özellik" özeti (bkz. contract).
 * Herhangi bir hata → boş highlights ile döner, asla fırlatmaz.
 */
class GeminiIdentitySummarizer implements GarmentIdentitySummarizerContract
{
    public function __construct(private GeminiClient $client) {}

    public function summarize(string $garmentImagePath): array
    {
        try {
            $raw        = $this->client->analyzeImage($this->buildPrompt(), $garmentImagePath);
            $highlights = $this->parse($raw);
        } catch (Throwable $e) {
            Log::warning('Creative giysi kimlik özeti başarısız.', ['reason' => $e->getMessage()]);
            $highlights = [];
        }

        return [
            'analysis_version' => 1,
            'model'            => (string) config('creative.ai.gemini.text_model', config('creative.ai.gemini.model')),
            'prompt_version'   => (int) config('creative.garment_detection.analysis.prompt_version', 1),
            'generated_at'     => now()->toIso8601String(),
            'highlights'       => $highlights,
        ];
    }

    private function buildPrompt(): string
    {
        return <<<'PROMPT'
        You are a garment inspection expert. Look at this product photo and list, in
        order of importance, the visual details that most distinguish THIS SPECIFIC
        garment from other similar garments — the things a customer would notice
        first and that must be preserved exactly in any re-rendering (e.g. an
        unusual print, a distinctive collar shape, a hardware detail, an embroidery,
        a color-block pattern). Do NOT list generic properties every garment of its
        type has (e.g. plain "sleeves" or "fabric").

        Return AT MOST 5 items, each a short phrase (3-6 words), ordered from most
        to least distinguishing. Respond with ONLY a JSON array of strings, no other
        text, no markdown fences. Example: ["Pink floral embroidery", "Pearl buttons",
        "Peter Pan collar"]. If the garment has no notable distinguishing details,
        return an empty array [].
        PROMPT;
    }

    /**
     * @return array<int,string>
     */
    private function parse(string $raw): array
    {
        if (preg_match('/\[.*\]/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return collect($decoded)
                    ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                    ->map(fn ($v) => trim($v))
                    ->take(5)
                    ->values()
                    ->all();
            }
        }

        return [];
    }
}
