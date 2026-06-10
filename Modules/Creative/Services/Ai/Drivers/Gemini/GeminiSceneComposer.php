<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\SceneRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini ile model/sahne kurgusu üreten compose sürücüsü.
 */
class GeminiSceneComposer implements SceneComposerContract
{
    public function __construct(
        private GeminiClient $client,
        private GeminiPromptBuilder $prompts,
    ) {}

    public function compose(SceneRequest $request): string
    {
        $prompt = $this->prompts->build($request);

        // Ürün görseli varsa Gemini'ye stil/renk referansı olarak verilir.
        $refs = array_filter([$request->productImagePath]);

        $bytes = $this->client->generateImage($prompt, $refs);

        return ImageFile::temp($bytes, 'png');
    }
}
