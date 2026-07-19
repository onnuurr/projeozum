<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use Modules\Creative\Services\Ai\Contracts\GarmentTryOnContract;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * fal anahtarı yokken devreye giren placeholder try-on sürücüsü.
 * Gerçek giydirme yapmaz; model görselini kopyalayıp pipeline'ı tamamlar.
 */
class MockTryOn implements GarmentTryOnContract
{
    public function __construct(private MockImageFactory $images) {}

    public function tryOn(string $modelImagePath, string $garmentImagePath, array $extraGarmentImages = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string
    {
        // Model görseli okunabiliyorsa onu, değilse yeni bir placeholder döndür.
        if (is_file($modelImagePath)) {
            $bin = (string) file_get_contents($modelImagePath);
            if ($bin !== '') {
                return ImageFile::temp($bin, 'png');
            }
        }

        return ImageFile::temp($this->images->make(768, 1024, '#2e3440', 'MOCK TRY-ON'), 'png');
    }

    public function modelIdentifier(): string
    {
        return 'mock';
    }
}
