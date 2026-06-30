<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\PosePreviewComposerContract;
use Modules\Creative\Services\Ai\PoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini anahtarı yokken devreye giren placeholder poz önizleme sürücüsü.
 */
class MockPosePreviewComposer implements PosePreviewComposerContract
{
    public function __construct(private MockImageFactory $images) {}

    public function compose(PoseRequest $request): string
    {
        $bytes = $this->images->make(768, 1024, '#64748b', 'MOCK POSE: ' . $request->label);

        return ImageFile::temp($bytes, 'png');
    }
}
