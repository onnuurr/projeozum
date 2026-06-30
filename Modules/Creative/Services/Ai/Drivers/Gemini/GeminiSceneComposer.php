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

        // Ürünün kendi görselleri Gemini'ye konu/sadakat referansı olarak verilir.
        $refs = $request->productImagePaths ?: array_filter([$request->productImagePath]);
        $refs = array_values(array_filter($refs, fn ($p) => is_string($p) && is_file($p)));

        $bytes = $this->client->generateImage($prompt, $refs);

        return ImageFile::temp($bytes, 'png');
    }
}
