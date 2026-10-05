<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\CloseupRequest;
use Modules\Creative\Services\Ai\Contracts\CloseupComposerContract;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Gemini anahtarı yokken devreye giren placeholder yakın plan sürücüsü — kaynak
 * görseli kopyalayıp pipeline'ı tamamlar (dev/test).
 */
class MockCloseupComposer implements CloseupComposerContract
{
    public function __construct(private MockImageFactory $images) {}

    public function compose(CloseupRequest $request): string
    {
        if (is_file($request->sourceImagePath)) {
            $bin = (string) file_get_contents($request->sourceImagePath);
            if ($bin !== '') {
                return ImageFile::temp($bin, 'png');
            }
        }

        return ImageFile::temp($this->images->make(768, 1024, '#2e3440', 'MOCK CLOSE-UP'), 'png');
    }

    public function modelIdentifier(): string
    {
        return 'mock';
    }
}
