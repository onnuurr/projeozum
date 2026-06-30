<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\MannequinComposerContract;
use Modules\Creative\Services\Ai\MannequinRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini ile sanal manken kimlik görseli üreten compose sürücüsü.
 */
class GeminiMannequinComposer implements MannequinComposerContract
{
    public function __construct(
        private GeminiClient $client,
        private MannequinPromptBuilder $prompts,
    ) {}

    public function compose(MannequinRequest $request): string
    {
        $prompt = $this->prompts->build($request);
        $bytes  = $this->client->generateImage($prompt);

        return ImageFile::temp($bytes, 'png');
    }
}
