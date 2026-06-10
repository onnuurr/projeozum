<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\SceneComposerContract;
use Modules\Creative\Services\Ai\SceneRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini anahtarı yokken devreye giren placeholder compose sürücüsü.
 */
class MockSceneComposer implements SceneComposerContract
{
    public function __construct(private MockImageFactory $images) {}

    public function compose(SceneRequest $request): string
    {
        $color = $request->palette['primary'] ?? '#3b4252';
        $bytes = $this->images->make(768, 1024, $color, 'MOCK SCENE: ' . $request->productName);

        return ImageFile::temp($bytes, 'png');
    }
}
