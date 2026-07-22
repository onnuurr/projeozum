<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Illuminate\Support\Facades\Http;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * Google Gemini görsel üretim istemcisi (generateContent).
 *
 * Görsel üretiminde responseModalities = [TEXT, IMAGE] zorunludur; yalnız IMAGE
 * istenirse API 400 döner (bkz. memory reference_gemini_image_modalities).
 */
class GeminiClient
{
    /**
     * Verilen prompt (ve opsiyonel referans görseller) ile bir görsel üretir.
     *
     * @param  array<int,string>   $imagePaths   inline referans görsellerin yerel yolları
     * @param  string|null         $model        Model override (boşsa config'teki varsayılan)
     * @param  array<string,mixed> $imageConfig  generationConfig.imageConfig (ör. aspectRatio,
     *                                            imageSize). Boş/verilmezse GÖNDERİLMEZ — bir model
     *                                            alanı reddederse davranış değişmesin diye.
     * @return string  Üretilen görselin ham baytları (PNG)
     */
    public function generateImage(string $prompt, array $imagePaths = [], ?string $model = null, array $imageConfig = []): string
    {
        $apiKey = (string) config('creative.ai.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY tanımlı değil.');
        }

        $parts = [['text' => $prompt]];
        foreach ($imagePaths as $path) {
            if (is_string($path) && is_file($path)) {
                $parts[] = [
                    'inline_data' => [
                        'mime_type' => ImageFile::mime($path),
                        'data'      => ImageFile::base64($path),
                    ],
                ];
            }
        }

        $base  = rtrim((string) config('creative.ai.gemini.base_url'), '/');
        $model = $model ?: (string) config('creative.ai.gemini.model');

        $generationConfig = [
            'responseModalities' => config('creative.ai.gemini.modalities', ['TEXT', 'IMAGE']),
        ];

        // Yalnız gerçekten değer verildiğinde imageConfig gönder (boş alanlar atılır).
        $imageConfig = array_filter($imageConfig, fn ($v) => $v !== null && $v !== '');
        if ($imageConfig !== []) {
            $generationConfig['imageConfig'] = $imageConfig;
        }

        $response = Http::timeout((int) config('creative.ai.timeout', 240))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("{$base}/models/{$model}:generateContent", [
                'contents' => [['parts' => $parts]],
                'generationConfig' => $generationConfig,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Gemini isteği başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return $this->extractImage($response->json() ?? []);
    }

    /**
     * generationConfig.imageConfig için verilen aspectRatio/imageSize'ı filtreler.
     * Boş değerler atılır (gönderilmez) — bir model alanı reddederse ilgili
     * env'i boşaltmak yeter, davranış otomatik eskiye döner.
     *
     * @return array<string,string>
     */
    public static function buildImageConfig(?string $aspectRatio, ?string $imageSize): array
    {
        return array_filter([
            'aspectRatio' => (string) $aspectRatio,
            'imageSize'   => (string) $imageSize,
        ], fn ($v) => $v !== '');
    }

    /**
     * config('creative.ai.gemini.image.*') üzerinden varsayılan imageConfig.
     * Kimlik ve poz adımları bunu kullanır (ikisi de varsayılan olarak
     * 2.5-flash-image'e düşebildiği için 'size' varsayılan olarak boş bırakılır).
     *
     * @return array<string,string>
     */
    public static function defaultImageConfig(): array
    {
        return self::buildImageConfig(
            (string) config('creative.ai.gemini.image.aspect_ratio', ''),
            (string) config('creative.ai.gemini.image.size', ''),
        );
    }

    /**
     * Verilen prompt ile düz metin üretir (caption/hashtag gibi).
     *
     * Aynı generateContent endpoint'i kullanılır; ancak responseModalities
     * GÖNDERİLMEZ — varsayılan TEXT yanıt döner. Yanıttaki tüm metin parçaları
     * birleştirilir.
     */
    public function generateText(string $prompt): string
    {
        $apiKey = (string) config('creative.ai.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY tanımlı değil.');
        }

        $base  = rtrim((string) config('creative.ai.gemini.base_url'), '/');
        $model = (string) config('creative.ai.gemini.text_model', config('creative.ai.gemini.model'));

        $response = Http::timeout((int) config('creative.ai.timeout', 240))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("{$base}/models/{$model}:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Gemini metin isteği başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return $this->extractText($response->json() ?? []);
    }

    /**
     * Bir görsel + metin prompt verip düz metin (genelde JSON) üretir — mevcut
     * generateImage() (görsel girdi, GÖRSEL çıktı) ile generateText() (salt metin
     * girdi, metin çıktı) arasındaki boşluğu kapatır: GÖRSEL girdi + METİN çıktı.
     * Giysi parça analizi (renk/desen/doku) ve bütünsel kimlik özeti bu metodu kullanır.
     */
    public function analyzeImage(string $prompt, string $imagePath): string
    {
        $apiKey = (string) config('creative.ai.gemini.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY tanımlı değil.');
        }
        if (! is_file($imagePath)) {
            throw new RuntimeException('Analiz edilecek görsel bulunamadı: ' . $imagePath);
        }

        $parts = [
            ['text' => $prompt],
            ['inline_data' => [
                'mime_type' => ImageFile::mime($imagePath),
                'data'      => ImageFile::base64($imagePath),
            ]],
        ];

        $base  = rtrim((string) config('creative.ai.gemini.base_url'), '/');
        $model = (string) config('creative.ai.gemini.text_model', config('creative.ai.gemini.model'));

        $response = Http::timeout((int) config('creative.ai.timeout', 240))
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->post("{$base}/models/{$model}:generateContent", [
                'contents' => [['parts' => $parts]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Gemini görsel analiz isteği başarısız (HTTP %d): %s',
                $response->status(),
                substr($response->body(), 0, 500),
            ));
        }

        return $this->extractText($response->json() ?? []);
    }

    /**
     * generateContent yanıtından tüm metin parçalarını birleştirir.
     *
     * @param  array<string,mixed>  $json
     */
    private function extractText(array $json): string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];

        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Gemini yanıtında metin verisi bulunamadı.');
        }

        return $text;
    }

    /**
     * generateContent yanıtından ilk inline görsel baytını çıkarır.
     *
     * @param  array<string,mixed>  $json
     */
    private function extractImage(array $json): string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            // API camelCase (inlineData) veya snake_case (inline_data) dönebilir.
            $data = $part['inlineData']['data'] ?? $part['inline_data']['data'] ?? null;
            if (is_string($data) && $data !== '') {
                $bin = base64_decode($data, true);
                if ($bin !== false && $bin !== '') {
                    return $bin;
                }
            }
        }

        throw new RuntimeException('Gemini yanıtında görsel verisi bulunamadı.');
    }
}
