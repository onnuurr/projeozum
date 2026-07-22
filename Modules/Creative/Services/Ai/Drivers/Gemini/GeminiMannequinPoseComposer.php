<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\MannequinPoseComposerContract;
use Modules\Creative\Services\Ai\MannequinPoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini ile manken pozu üreten compose sürücüsü.
 * Manken referans görseli kimlik referansı olarak modele verilir.
 */
class GeminiMannequinPoseComposer implements MannequinPoseComposerContract
{
    public function __construct(
        private GeminiClient $client,
        private MannequinPosePromptBuilder $prompts,
    ) {}

    public function compose(MannequinPoseRequest $request): string
    {
        $prompt = $this->prompts->build($request);

        // 1. referans: manken kimliği. 2. referans (varsa): poz şablonu (birebir duruş).
        $refs = [];
        if (is_file($request->referenceImagePath)) {
            $refs[] = $request->referenceImagePath;
        }
        if ($request->posePreviewPath !== null && is_file($request->posePreviewPath)) {
            $refs[] = $request->posePreviewPath;
        }

        // Poz görseli sonraki adımda try-on'a "kişi" referansı olarak girer;
        // düşük çözünürlükte üretilirse try-on çıktısı da o tavana sıkışır.
        $bytes = $this->client->generateImage($prompt, $refs, null, GeminiClient::defaultImageConfig());

        return ImageFile::temp($bytes, 'png');
    }
}
