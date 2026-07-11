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

        // Kimlik (yüz) burada doğduğu için: opsiyonel güçlü model + tam boy kadraj
        // (aspectRatio) / çözünürlük. Değerler boşsa generateImage bunları göndermez.
        $bytes = $this->client->generateImage(
            $prompt,
            [],
            (string) config('creative.ai.gemini.mannequin_model') ?: null,
            $this->imageConfig(),
        );

        return ImageFile::temp($bytes, 'png');
    }

    /**
     * generationConfig.imageConfig için config'ten aspectRatio/imageSize.
     * Boş değerler atılır (gönderilmez).
     *
     * @return array<string,string>
     */
    private function imageConfig(): array
    {
        return array_filter([
            'aspectRatio' => (string) config('creative.ai.gemini.image.aspect_ratio', ''),
            'imageSize'   => (string) config('creative.ai.gemini.image.size', ''),
        ], fn ($v) => $v !== '');
    }
}
