<?php

namespace Modules\Creative\Services;

use Modules\Creative\Models\CreativeTemplate;
use Modules\Creative\Services\Ai\Contracts\CopyGeneratorContract;
use Modules\Creative\Services\Ai\CopyRequest;
use Modules\Creative\Services\Ai\Support\CopySlots;
use Modules\Product\Models\Product;

/**
 * Şablonun metin slotları için marka tonunda on-image pazarlama metni (headline/
 * sub/cta) üreten yüksek seviye servis.
 *
 * Markanın "copy" kimliğini (brief/tone/cta_phrases/banned_words) BrandTokenService'ten,
 * ürün verisini modelden alır; yalnız semantik "copy" slotlarını (bkz. CopySlots)
 * sürücüye (Gemini/mock) devreder. "product_name" gibi birebir alanlar KAPSAM DIŞIDIR
 * (onları CreativeRenderService doğrudan ürün adıyla doldurur).
 */
class CopyService
{
    public function __construct(
        private CopyGeneratorContract $generator,
        private BrandTokenService $brandTokens,
    ) {}

    /**
     * Şablonun copy slotları için metin üretir. Copy slotu yoksa boş dizi döner
     * (sürücü hiç çağrılmaz).
     *
     * @return array<string,string>  slotKey => text
     */
    public function forTemplate(
        Product $product,
        CreativeTemplate $template,
        ?string $aspectLabel = null,
        ?string $extraInstructions = null,
    ): array {
        $slotKeys = $this->copySlotKeys($template);
        if ($slotKeys === []) {
            return [];
        }

        $product->loadMissing('category');

        $copy = $this->brandTokens->tokens()['copy'] ?? [];

        $attributes = array_filter([
            'materyal' => $product->material,
            'cinsiyet' => $product->gender,
        ], fn ($v) => is_string($v) && $v !== '');

        $request = new CopyRequest(
            productName: (string) $product->name,
            category: $product->category?->name,
            attributes: $attributes,
            slotKeys: $slotKeys,
            brief: (string) ($copy['brief'] ?? ''),
            tone: (string) ($copy['tone'] ?? ''),
            ctaPhrases: (array) ($copy['cta_phrases'] ?? []),
            bannedWords: (array) ($copy['banned_words'] ?? []),
            aspectLabel: $aspectLabel,
            extraInstructions: $extraInstructions,
        );

        return $this->generator->generate($request);
    }

    /**
     * Şablonun AI metni üretilebilir (copy) metin slot anahtarları.
     *
     * @return array<int,string>
     */
    private function copySlotKeys(CreativeTemplate $template): array
    {
        $keys = [];
        foreach ((array) ($template->slots ?? []) as $slot) {
            if (! is_array($slot) || ($slot['type'] ?? null) !== 'text') {
                continue;
            }
            $key = (string) ($slot['key'] ?? '');
            if ($key !== '' && CopySlots::isCopyKey($key)) {
                $keys[] = $key;
            }
        }

        return array_values(array_unique($keys));
    }
}
