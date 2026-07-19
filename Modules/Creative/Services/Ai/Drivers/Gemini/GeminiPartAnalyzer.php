<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Contracts\GarmentPartAnalyzerContract;
use Throwable;

/**
 * Gemini Vision ile giysi parça analizi. Kapalı sözlükten (enum) seçim
 * zorunlu tutulur — {@see GeminiCaptionGenerator} ile aynı "SADECE JSON"
 * + regex-extract + fallback deseni. Herhangi bir hata → tüm alanlar
 * confidence=0 ile döner, asla fırlatmaz (bkz. contract dokümantasyonu).
 */
class GeminiPartAnalyzer implements GarmentPartAnalyzerContract
{
    public function __construct(private GeminiClient $client) {}

    public function analyze(string $cropImagePath, string $labelKey, string $labelDisplay, string $preservationCategory): array
    {
        try {
            $raw  = $this->client->analyzeImage($this->buildPrompt($labelDisplay, $preservationCategory), $cropImagePath);
            $data = $this->parse($raw);
        } catch (Throwable $e) {
            Log::warning('Creative giysi parça analizi başarısız.', ['reason' => $e->getMessage(), 'label' => $labelKey]);
            $data = $this->emptyData();
        }

        return [
            'analysis_version' => 1,
            'model'            => (string) config('creative.ai.gemini.text_model', config('creative.ai.gemini.model')),
            'prompt_version'   => (int) config('creative.garment_detection.analysis.prompt_version', 1),
            'generated_at'     => now()->toIso8601String(),
            'data'             => $data,
        ];
    }

    private function buildPrompt(string $labelDisplay, string $category): string
    {
        $enums = (array) config('creative.garment_detection.analysis.enums', []);
        $priorityValues = implode(', ', $enums['priority'] ?? ['critical', 'high', 'medium', 'low']);

        $enumLines = '';
        foreach (['color', 'pattern', 'texture', 'fabric', 'stitching', 'hardware_type'] as $field) {
            $values = implode(', ', $enums[$field] ?? []);
            $enumLines .= "- {$field}: {$values}\n";
        }

        return <<<PROMPT
        You are a garment inspection expert analyzing a CLOSE-UP CROP of a single
        product detail. The part shown is: "{$labelDisplay}" (category: {$category}).

        For EACH of the following fields, choose EXACTLY ONE value from its allowed
        list. If nothing in the list genuinely matches what you see, use "OTHER" and
        describe what you actually see in that field's raw_text (otherwise raw_text
        is null). Give a confidence score (0.0-1.0) for each field reflecting how
        certain you are — if you cannot tell, use a LOW confidence rather than guessing
        confidently.

        Allowed values per field:
        {$enumLines}
        Also assess instance_priority: how critical is it that THIS EXACT crop's
        appearance is preserved unaltered by a downstream image generator (allowed:
        {$priorityValues}).

        Respond with ONLY this JSON shape, no other text, no markdown fences:
        {"instance_priority":{"value":"...","confidence":0.0},
         "color":{"value":"...","raw_text":null,"confidence":0.0},
         "pattern":{"value":"...","raw_text":null,"confidence":0.0},
         "texture":{"value":"...","raw_text":null,"confidence":0.0},
         "fabric":{"value":"...","raw_text":null,"confidence":0.0},
         "stitching":{"value":"...","raw_text":null,"confidence":0.0},
         "hardware_type":{"value":"...","raw_text":null,"confidence":0.0},
         "notes":"..."}
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
        $field = function (string $key) use ($decoded): array {
            $f = is_array($decoded[$key] ?? null) ? $decoded[$key] : [];

            return [
                'value'      => isset($f['value']) && $f['value'] !== '' ? (string) $f['value'] : null,
                'raw_text'   => isset($f['raw_text']) && $f['raw_text'] !== '' ? (string) $f['raw_text'] : null,
                'confidence' => isset($f['confidence']) ? max(0.0, min(1.0, (float) $f['confidence'])) : 0.0,
            ];
        };

        $priority = is_array($decoded['instance_priority'] ?? null) ? $decoded['instance_priority'] : [];

        return [
            'instance_priority' => [
                'value'      => isset($priority['value']) && $priority['value'] !== '' ? (string) $priority['value'] : null,
                'confidence' => isset($priority['confidence']) ? max(0.0, min(1.0, (float) $priority['confidence'])) : 0.0,
            ],
            'color'         => $field('color'),
            'pattern'       => $field('pattern'),
            'texture'       => $field('texture'),
            'fabric'        => $field('fabric'),
            'stitching'     => $field('stitching'),
            'hardware_type' => $field('hardware_type'),
            'notes'         => isset($decoded['notes']) && trim((string) $decoded['notes']) !== '' ? trim((string) $decoded['notes']) : null,
        ];
    }

    private function emptyData(): array
    {
        $empty = ['value' => null, 'raw_text' => null, 'confidence' => 0.0];

        return [
            'instance_priority' => ['value' => null, 'confidence' => 0.0],
            'color'             => $empty,
            'pattern'           => $empty,
            'texture'           => $empty,
            'fabric'            => $empty,
            'stitching'         => $empty,
            'hardware_type'     => $empty,
            'notes'             => null,
        ];
    }
}
