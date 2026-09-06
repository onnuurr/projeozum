<?php

namespace Modules\Atelier\Services\Ai\Drivers\Gemini;

use Illuminate\Support\Facades\Log;
use Modules\Atelier\Services\Ai\Contracts\PatternMetadataExtractorContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;
use Throwable;

/**
 * Gemini Vision ile kalıp kapak sayfasından ürün tipi + beden aralığı tahmini.
 *
 * Creative modülünün paylaşılan {@see GeminiClient}'ını kullanır (AI infra
 * paylaşımı — domain prompt/parse mantığı burada, Creative'e karışmaz).
 * Kapalı sözlükten (enum) seçim zorunlu tutulur — GeminiPartAnalyzer ile AYNI
 * "SADECE JSON" + regex-extract + fallback deseni. Herhangi bir hata → tüm
 * alanlar confidence=0 ile döner, asla fırlatmaz.
 */
class GeminiPatternMetadataExtractor implements PatternMetadataExtractorContract
{
    public function __construct(private GeminiClient $client) {}

    public function extract(string $coverImagePath): array
    {
        try {
            $raw  = $this->client->analyzeImage($this->buildPrompt(), $coverImagePath);
            $data = $this->parse($raw);
        } catch (Throwable $e) {
            Log::warning('Atelier kalıp metadata AI çıkarımı başarısız.', ['reason' => $e->getMessage()]);
            $data = $this->emptyData();
        }

        return [
            'analysis_version' => 1,
            'model'            => (string) config('creative.ai.gemini.text_model', config('creative.ai.gemini.model')),
            'prompt_version'   => (int) config('atelier.pattern_metadata_ai.prompt_version', 1),
            'generated_at'     => now()->toIso8601String(),
            'data'             => $data,
        ];
    }

    private function buildPrompt(): string
    {
        $enum = implode(', ', (array) config('atelier.pattern_metadata_ai.product_type_enum', []));

        return <<<PROMPT
        You are looking at the COVER PAGE of a sewing pattern PDF (may be in Russian,
        Dutch, German, English, or another language — read whatever text/labels are
        visible, including any product photo shown).

        Determine two things:

        1. product_type — the garment type. Choose EXACTLY ONE value from this list:
           {$enum}
           If nothing in the list genuinely matches, use "OTHER" and put a SHORT
           garment type name in raw_text, TRANSLATED INTO TURKISH (1-3 Turkish
           words, e.g. "tulum", "kot ceket") — NOT a sentence, NOT English, NOT
           an explanation. If you cannot even name a short garment type (e.g.
           the page shows only an isolated pattern piece with no product
           photo), leave raw_text null and give this field LOW confidence
           instead of writing an explanation.

        2. size_range — the size range this pattern covers, exactly as printed
           (e.g. "68-128", "134-158", "42-50", "XS-XL"). If no size range is visible
           anywhere on the page, value is null.

        Give a confidence score (0.0-1.0) for EACH field reflecting how certain you
        are — if you cannot tell, use a LOW confidence rather than guessing confidently.

        Respond with ONLY this JSON shape, no other text, no markdown fences:
        {"product_type":{"value":"...","raw_text":null,"confidence":0.0},
         "size_range":{"value":"...","confidence":0.0}}
        PROMPT;
    }

    private function parse(string $raw): array
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $this->normalize($decoded);
            }
        }

        return $this->emptyData();
    }

    private function normalize(array $decoded): array
    {
        $productType = is_array($decoded['product_type'] ?? null) ? $decoded['product_type'] : [];
        $sizeRange   = is_array($decoded['size_range'] ?? null) ? $decoded['size_range'] : [];

        return [
            'product_type' => [
                'value'      => isset($productType['value']) && $productType['value'] !== '' ? (string) $productType['value'] : null,
                'raw_text'   => isset($productType['raw_text']) && $productType['raw_text'] !== '' ? (string) $productType['raw_text'] : null,
                'confidence' => isset($productType['confidence']) ? max(0.0, min(1.0, (float) $productType['confidence'])) : 0.0,
            ],
            'size_range' => [
                'value'      => isset($sizeRange['value']) && $sizeRange['value'] !== '' ? (string) $sizeRange['value'] : null,
                'confidence' => isset($sizeRange['confidence']) ? max(0.0, min(1.0, (float) $sizeRange['confidence'])) : 0.0,
            ],
        ];
    }

    private function emptyData(): array
    {
        return [
            'product_type' => ['value' => null, 'raw_text' => null, 'confidence' => 0.0],
            'size_range'   => ['value' => null, 'confidence' => 0.0],
        ];
    }
}
