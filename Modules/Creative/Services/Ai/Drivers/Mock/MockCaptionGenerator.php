<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\CaptionRequest;
use Modules\Creative\Services\Ai\Contracts\CaptionGeneratorContract;
use Modules\Creative\Services\Ai\Support\HashtagHelper;

/**
 * Gemini anahtarı yokken devreye giren şablon tabanlı caption sürücüsü.
 */
class MockCaptionGenerator implements CaptionGeneratorContract
{
    /**
     * @return array{caption:string,hashtags:array<int,string>}
     */
    public function generate(CaptionRequest $request): array
    {
        $name    = $request->productName;
        $caption = "{$name} ✨ Yeni sezonun öne çıkan parçası şimdi koleksiyonumuzda. Kaçırma!";

        // Hashtag'leri ürün adı, kategori ve niteliklerden türet.
        $seeds = array_merge(
            preg_split('/\s+/', $name) ?: [],
            array_filter([$request->category]),
            array_values($request->attributes),
            ['moda', 'yeni sezon', 'alışveriş'],
        );

        return [
            'caption'  => $caption,
            'hashtags' => HashtagHelper::normalize($seeds, 8),
        ];
    }
}
