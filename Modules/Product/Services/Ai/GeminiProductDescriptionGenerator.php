<?php

namespace Modules\Product\Services\Ai;

use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;
use Modules\Product\Models\Product;
use Modules\Product\Services\Ai\Contracts\ProductDescriptionGenerator;
use Modules\Product\Services\Ai\Exceptions\AiGenerationException;
use Modules\Tenant\Models\Tenant;
use Throwable;

class GeminiProductDescriptionGenerator implements ProductDescriptionGenerator
{
    public function __construct(
        private GeminiClient $client,
        private PromptMaterialResolver $materials,
        private ProductDescriptionPromptBuilder $builder,
    ) {}

    public function generate(Product $product, ?Tenant $tenant = null): ProductDescriptionResult
    {
        $materialLines = $this->materials->forPrompt($product);
        $prompt        = $this->builder->build($product, $materialLines, $tenant);

        try {
            $raw = $this->client->generateText($prompt);
        } catch (Throwable $e) {
            throw new AiGenerationException(
                'AI çağrısı başarısız: ' . $e->getMessage(),
                previous: $e,
            );
        }

        $parsed = $this->parseJson($raw);

        $public = trim((string) ($parsed['public_description'] ?? ''));
        $tenantDesc = trim((string) ($parsed['tenant_description'] ?? ''));

        if ($public === '' || $tenantDesc === '') {
            throw new AiGenerationException(
                'AI cevabı beklenen alanları içermiyor (public_description / tenant_description).',
            );
        }

        // SEO alanları opsiyoneldir: model üretmezse null bırakılır (mevcut akış bozulmaz).
        return new ProductDescriptionResult(
            publicDescription: $public,
            tenantDescription: $tenantDesc,
            model: (string) config('creative.ai.gemini.text_model', 'gemini-2.5-flash'),
            publicName: $this->cleanOrNull($parsed['public_name'] ?? null, 60),
            metaTitle: $this->cleanOrNull($parsed['meta_title'] ?? null, 60),
            metaDescription: $this->cleanOrNull($parsed['meta_description'] ?? null, 155),
            metaKeywords: $this->cleanOrNull($parsed['meta_keywords'] ?? null, 255),
        );
    }

    /**
     * Boş/whitespace ise null; aksi halde trim'lenip $max karaktere kırpılır.
     * SEO alanlarının veritabanı kolon sınırlarını aşmamasını garantiler.
     */
    private function cleanOrNull(mixed $value, int $max): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    /**
     * Gemini bazen ```json ... ``` ile sarabilir; hem düz JSON hem code-fence'i toleranslı parse et.
     */
    private function parseJson(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            throw new AiGenerationException('AI boş cevap döndürdü.');
        }

        // Code fence temizle.
        if (preg_match('/```(?:json)?\s*(.+?)\s*```/s', $text, $m)) {
            $text = trim($m[1]);
        }

        // İlk { ile son } arasını kes (bazen leading/trailing metin gelir).
        $start = strpos($text, '{');
        $end   = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new AiGenerationException('AI cevabı JSON olarak parse edilemedi.');
        }

        return $decoded;
    }
}
