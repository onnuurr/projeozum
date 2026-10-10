<?php

namespace Modules\Product\Services\Ai;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiRequestException;
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
        private ProductSeoRules $seoRules,
    ) {}

    public function generate(
        Product $product,
        ?Tenant $tenant = null,
        array $parts = [ProductDescriptionPromptBuilder::PART_DESCRIPTIONS],
        ?Collection $materialLines = null,
    ): ProductDescriptionResult {
        $materialLines ??= $this->materials->forPrompt($product);
        $prompt        = $this->builder->build($product, $materialLines, $tenant, $parts);

        try {
            $raw = $this->client->generateText($prompt);
        } catch (GeminiRequestException $e) {
            // Mesaj creative::messages.ai_provider.*'dan gelir; ham sağlayıcı cevabı GeminiClient'ta loglandı.
            throw new AiGenerationException(
                __('product::messages.ai.call_failed', ['error' => $e->getMessage()]),
                previous: $e,
            );
        } catch (Throwable $e) {
            Log::error('Ürün açıklaması AI çağrısı beklenmedik hata.', ['product_id' => $product->id, 'exception' => $e]);
            throw new AiGenerationException(
                __('product::messages.ai.call_failed', ['error' => __('product::messages.ai.unexpected')]),
                previous: $e,
            );
        }

        $parsed = $this->parseJson($raw);

        $public = $tenantDesc = $publicName = null;
        if (in_array(ProductDescriptionPromptBuilder::PART_DESCRIPTIONS, $parts, true)) {
            $public = trim((string) ($parsed['public_description'] ?? ''));
            $tenantDesc = trim((string) ($parsed['tenant_description'] ?? ''));
            // Ad da SEO metinleri gibi kural katmanından geçer (markdown/tırnak temizliği, 70 karakter).
            $publicName = $this->seoRules->text($parsed['public_name'] ?? null, ProductSeoRules::PUBLIC_NAME_MAX);

            if ($public === '' || $tenantDesc === '' || $publicName === null) {
                throw new AiGenerationException(__('product::messages.ai.missing_fields'));
            }
        }

        $seo = ['meta_title' => null, 'meta_description' => null, 'meta_keywords' => null];
        if (in_array(ProductDescriptionPromptBuilder::PART_SEO, $parts, true)) {
            // Ham model çıktısı forma doğrudan gitmez: uzunluk/biçim kuralları burada zorlanır.
            $seo = $this->seoRules->apply(is_array($parsed['seo'] ?? null) ? $parsed['seo'] : []);

            if ($seo['meta_title'] === null || $seo['meta_description'] === null) {
                throw new AiGenerationException(__('product::messages.ai.missing_seo_fields'));
            }
        }

        return new ProductDescriptionResult(
            publicDescription: $public,
            publicName: $publicName,
            tenantDescription: $tenantDesc,
            model: (string) config('creative.ai.gemini.text_model', 'gemini-2.5-flash'),
            metaTitle: $seo['meta_title'],
            metaDescription: $seo['meta_description'],
            metaKeywords: $seo['meta_keywords'],
        );
    }

    /**
     * Gemini bazen ```json ... ``` ile sarabilir; hem düz JSON hem code-fence'i toleranslı parse et.
     */
    private function parseJson(string $raw): array
    {
        $text = trim($raw);
        if ($text === '') {
            throw new AiGenerationException(__('product::messages.ai.empty_response'));
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
            throw new AiGenerationException(__('product::messages.ai.invalid_json'));
        }

        return $decoded;
    }
}
