<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\MannequinPoseComposerContract;
use Modules\Creative\Services\Ai\MannequinPoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini anahtarı yokken devreye giren placeholder poz sürücüsü.
 */
class MockMannequinPoseComposer implements MannequinPoseComposerContract
{
    public function __construct(private MockImageFactory $images) {}

    public function compose(MannequinPoseRequest $request): string
    {
        $bytes = $this->images->make(768, 1024, '#4b5563', 'MOCK POSE: ' . $request->label);

        return ImageFile::temp($bytes, 'png');
    }
}
