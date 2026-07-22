<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\CaptionRequest;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Creative\Services\Ai\Support\HashtagHelper;

/**
 * Gemini metin modeli ile caption + hashtag üreten sürücü.
 */
class GeminiCaptionGenerator implements CaptionGeneratorContract
{
    public function __construct(private GeminiClient $client) {}

    /**
     * @return array{caption:string,hashtags:array<int,string>}
     */
    public function generate(CaptionRequest $request): array
    {
        $raw = $this->client->generateText($this->buildPrompt($request));

        return $this->parse($raw);
    }

    private function buildPrompt(CaptionRequest $request): string
    {
        $lines = ['Ürün: ' . $request->productName];

        if ($request->category) {
            $lines[] = 'Kategori: ' . $request->category;
        }

        foreach ($request->attributes as $key => $value) {
            $lines[] = ucfirst((string) $key) . ': ' . $value;
        }

        if ($request->palette) {
            $lines[] = 'Marka renkleri (ton ipucu): ' . implode(', ', $request->palette);
        }

        $context = implode("\n", $lines);

        $hashtagRule = $request->hashtagPool !== []
            ? '- Şu marka hashtag havuzundan alakalı olanları MUTLAKA dahil et: '
                . implode(', ', $request->hashtagPool)
                . '. Kalan slotları bu ürüne özel, keşif/reach amaçlı yeni hashtag\'lerle tamamla.'
            : '- 5-10 adet alakalı hashtag (Türkçe/İngilizce karışık olabilir).';

        return <<<PROMPT
        Sen bir moda/e-ticaret markasının sosyal medya editörüsün. Aşağıdaki ürün için
        Türkçe, akıcı ve marka tonuna uygun bir Instagram caption'ı ve ilgili hashtag'ler üret.

        Ürün bilgisi:
        {$context}

        Kurallar:
        - Caption 1-3 cümle, samimi ve satışı destekleyen bir ton. En fazla 1-2 emoji.
        {$hashtagRule}
        - SADECE şu JSON formatında yanıt ver, başka hiçbir metin ekleme:
        {"caption": "...", "hashtags": ["#etiket1", "#etiket2"]}
        PROMPT;
    }

    /**
     * @return array{caption:string,hashtags:array<int,string>}
     */
    private function parse(string $raw): array
    {
        // Yanıt ```json ... ``` bloğu içinde gelebilir; JSON gövdesini ayıkla.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && isset($decoded['caption'])) {
                return [
                    'caption'  => trim((string) $decoded['caption']),
                    'hashtags' => HashtagHelper::normalize((array) ($decoded['hashtags'] ?? [])),
                ];
            }
        }

        // JSON çözülemezse: metni caption say, içinden hashtag ayıkla.
        $hashtags = HashtagHelper::extractFromText($raw);
        $caption  = trim(preg_replace('/#([\p{L}\p{N}_]+)/u', '', $raw) ?? $raw);

        return [
            'caption'  => $caption !== '' ? $caption : trim($raw),
            'hashtags' => $hashtags,
        ];
    }
}
