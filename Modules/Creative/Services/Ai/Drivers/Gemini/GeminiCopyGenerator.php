<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\CopyRequest;
use Throwable;

/**
 * Gemini metin modeli ile marka kriterlerine uygun görsel-üstü metin
 * (headline/sub-headline/CTA) üreten sürücü. {@see GeminiPartAnalyzer} ile
 * aynı "SADECE JSON" + regex-extract + fallback deseni. Herhangi bir hata →
 * tüm alanlar confidence=0 ile döner, asla fırlatmaz (bkz. contract dokümantasyonu).
 */
class GeminiCopyGenerator implements CopyGeneratorContract
{
    public function __construct(private GeminiClient $client) {}

    public function generate(CopyRequest $request): array
    {
        try {
            $raw  = $this->client->generateText($this->buildPrompt($request));
            $data = $this->parse($raw);
        } catch (Throwable $e) {
            Log::warning('Creative AI copywriting (Gemini) başarısız.', ['reason' => $e->getMessage()]);
            $data = $this->emptyData();
        }

        return [
            'copy_version'   => 1,
            'model'          => (string) config('creative.ai.gemini.text_model', config('creative.ai.gemini.model')),
            'prompt_version' => (int) config('creative.ai.copy.prompt_version', 1),
            'generated_at'   => now()->toIso8601String(),
            'data'           => $data,
        ];
    }

    private function buildPrompt(CopyRequest $request): string
    {
        $lines = ['Ürün: ' . $request->productName];

        if ($request->category) {
            $lines[] = 'Kategori: ' . $request->category;
        }

        foreach ($request->attributes as $key => $value) {
            $lines[] = ucfirst((string) $key) . ': ' . $value;
        }

        $context = implode("\n", $lines);

        $rules = [];

        if ($request->designBrief) {
            $rules[] = 'Marka brief\'i (MUST follow): ' . $request->designBrief;
        }
        if ($request->tone) {
            $rules[] = 'Marka tonu (MUST follow): ' . $request->tone;
        }
        if ($request->ctaPhrases !== []) {
            $rules[] = 'CTA için SADECE şu ifadelerden birini, harfi harfine seç: '
                . implode(' | ', $request->ctaPhrases);
        } else {
            $rules[] = 'CTA kısa (en fazla 3 kelime) ve satışı destekleyen bir eylem çağrısı olsun.';
        }
        if ($request->bannedWords !== []) {
            $rules[] = 'Şu kelimeleri/ifadeleri KESİNLİKLE kullanma: ' . implode(', ', $request->bannedWords);
        }

        $ruleText = implode("\n", array_map(fn ($r) => '- ' . $r, $rules));

        return <<<PROMPT
        Sen bir moda/e-ticaret markasının sosyal medya tasarımcısısın. Aşağıdaki ürün için
        görselin ÜSTÜNE basılacak kısa bir başlık (headline), isteğe bağlı bir alt başlık
        (sub_headline) ve bir CTA buton yazısı üret. Türkçe yaz.

        Ürün bilgisi:
        {$context}

        Kurallar:
        {$ruleText}
        - headline en fazla 6 kelime, sub_headline en fazla 10 kelime, cta en fazla 3 kelime.
        - Her alan için 0.0-1.0 arası bir confidence ver — emin değilsen düşük confidence ver,
          asla yüksek confidence ile tahmin etme.
        - SADECE şu JSON formatında yanıt ver, başka hiçbir metin/markdown ekleme:
        {"headline":{"value":"...","confidence":0.0},
         "sub_headline":{"value":"...","confidence":0.0},
         "cta":{"value":"...","confidence":0.0}}
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
                'confidence' => isset($f['confidence']) ? max(0.0, min(1.0, (float) $f['confidence'])) : 0.0,
            ];
        };

        return [
            'headline'     => $field('headline'),
            'sub_headline' => $field('sub_headline'),
            'cta'          => $field('cta'),
        ];
    }

    private function emptyData(): array
    {
        $empty = ['value' => null, 'confidence' => 0.0];

        return [
            'headline'     => $empty,
            'sub_headline' => $empty,
            'cta'          => $empty,
        ];
    }
}
