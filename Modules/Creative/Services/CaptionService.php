<?php

namespace Modules\Creative\Services;

use Modules\Creative\Services\Ai\CaptionRequest;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Creative\Services\Ai\Support\HashtagHelper;
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

        $criteria    = $this->brandTokens->tokens()['criteria'] ?? [];
        $hashtagPool = $criteria['hashtag_pool'] ?? [];

        $request = new CaptionRequest(
            productName: (string) $product->name,
            category: $product->category?->name,
            attributes: $attributes,
            palette: $this->brandTokens->tokens()['palette'] ?? [],
            hashtagPool: $hashtagPool,
        );

        $result = $this->generator->generate($request);

        return [
            'caption'  => $result['caption'] ?? '',
            'hashtags' => $this->mergeHashtags($hashtagPool, $result['hashtags'] ?? []),
        ];
    }

    /**
     * Marka hashtag havuzu HER ZAMAN nihai sonuca dahil edilir (marka
     * tutarlılığı); AI'nin ürettiği kalanlar keşif/reach için tamamlar.
     * Havuz öğeleri dizinin BAŞINA konur ki HashtagHelper::normalize()'ın
     * azami sayı kesmesinde asla düşmesinler — sürücü (Gemini/mock) havuzu
     * hiç dikkate almasa bile bu garanti korunur.
     *
     * @param  array<int,string>  $pool
     * @param  array<int,string>  $aiHashtags
     * @return array<int,string>
     */
    private function mergeHashtags(array $pool, array $aiHashtags): array
    {
        if ($pool === []) {
            return $aiHashtags;
        }

        return HashtagHelper::normalize(array_merge($pool, $aiHashtags));
    }
}
