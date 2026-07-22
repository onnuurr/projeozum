<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\CopyRequest;
use Modules\Creative\Services\Ai\Support\CopyConstraints;

/**
 * Gemini metin modeli ile on-image pazarlama metni (headline/sub/cta) üreten sürücü.
 *
 * Model çıktısı JSON'dan istenen slot anahtarlarına eşlenir; ardından markanın
 * yasaklı kelimeleri KOD seviyesinde ayıklanır (CopyConstraints) — model talimatı
 * yok sayarsa bile marka güvenliği garanti edilir.
 */
class GeminiCopyGenerator implements CopyGeneratorContract
{
    public function __construct(
        private GeminiClient $client,
        private CopyPromptBuilder $prompts,
    ) {}

    /**
     * @return array<string,string>
     */
    public function generate(CopyRequest $request): array
    {
        if ($request->slotKeys === []) {
            return [];
        }

        $raw    = $this->client->generateText($this->prompts->build($request));
        $parsed = $this->parse($raw, $request->slotKeys);

        return CopyConstraints::sanitize($parsed, $request->bannedWords);
    }

    /**
     * Model JSON'unu yalnız istenen slot anahtarlarına indirger.
     *
     * @param  array<int,string>  $slotKeys
     * @return array<string,string>
     */
    private function parse(string $raw, array $slotKeys): array
    {
        $decoded = null;
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $decoded = json_decode($m[0], true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($slotKeys as $key) {
            $value = $decoded[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $out[$key] = trim($value);
            }
        }

        return $out;
    }
}
