<?php

namespace Modules\Creative\Services;

use Modules\Creative\Services\Ai\CaptionRequest;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Product\Models\Product;

/**
 * Ürün + marka bağlamından sosyal medya caption/hashtag üreten yüksek seviye servis.
 *
 * Marka tonunu BrandTokenService'ten, ürün verisini modelden alır; üretimi
 * config'e göre bağlanmış sürücüye (Gemini veya mock) devreder.
 */
class CaptionService
{
    public function __construct(
        private CaptionGeneratorContract $generator,
        private BrandTokenService $brandTokens,
    ) {}

    /**
     * Verilen ürün için caption + hashtag üretir.
     *
     * @return array{caption:string,hashtags:array<int,string>}
     */
    public function forProduct(Product $product): array
    {
        $product->loadMissing('category');

        $attributes = array_filter([
            'materyal' => $product->material,
            'cinsiyet' => $product->gender,
        ], fn ($v) => is_string($v) && $v !== '');

        $request = new CaptionRequest(
            productName: (string) $product->name,
            category: $product->category?->name,
            attributes: $attributes,
            palette: $this->brandTokens->tokens()['palette'] ?? [],
        );

        return $this->generator->generate($request);
    }
}
