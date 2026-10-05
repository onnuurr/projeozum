<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\CloseupRequest;
use Modules\Creative\Services\Ai\Contracts\CloseupComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini (try-on ile aynı görsel modeli) ile yakın plan / detay çekimi üretir.
 * Görsel sırası CloseupPromptBuilder ile eşleşir: 1) tam boy giydirme, 2) ürün
 * fotoğrafı, 3+) ürünün detay görselleri.
 */
class GeminiCloseupComposer implements CloseupComposerContract
{
    public function __construct(
        private GeminiClient $client,
        private CloseupPromptBuilder $prompts,
    ) {}

    public function compose(CloseupRequest $request): string
    {
        $images = [
            $request->sourceImagePath,
            $request->garmentImagePath,
            ...array_column($request->extraGarmentImages, 'path'),
        ];

        $imageConfig = GeminiClient::buildImageConfig(
            (string) config('creative.ai.gemini.image.aspect_ratio', ''),
            (string) config('creative.ai.gemini.image.tryon_size', ''),
        );

        $bytes = $this->client->generateImage(
            $this->prompts->build($request),
            $images,
            $this->modelIdentifier() ?: null,
            $imageConfig,
        );

        return ImageFile::temp($bytes, 'png');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.ai.gemini.tryon_model');
    }
}
