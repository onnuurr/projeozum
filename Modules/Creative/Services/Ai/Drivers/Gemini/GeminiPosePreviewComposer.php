<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\PosePreviewComposerContract;
use Modules\Creative\Services\Ai\PoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini ile nötr figür poz önizlemesi üreten compose sürücüsü.
 */
class GeminiPosePreviewComposer implements PosePreviewComposerContract
{
    public function __construct(
        private GeminiClient $client,
        private PosePromptBuilder $prompts,
    ) {}

    public function compose(PoseRequest $request): string
    {
        $prompt = $this->prompts->build($request);
        $bytes  = $this->client->generateImage($prompt);

        return ImageFile::temp($bytes, 'png');
    }
}
