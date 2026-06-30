<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\MannequinComposerContract;
use Modules\Creative\Services\Ai\MannequinRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini anahtarı yokken devreye giren placeholder manken sürücüsü.
 */
class MockMannequinComposer implements MannequinComposerContract
{
    public function __construct(private MockImageFactory $images) {}

    public function compose(MannequinRequest $request): string
    {
        $bytes = $this->images->make(768, 1024, '#5b6478', 'MOCK MANNEQUIN: ' . $request->name);

        return ImageFile::temp($bytes, 'png');
    }
}
