<?php

namespace Tests\Feature\Creative;

use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;

/** Python'suz görüntü adımları: maskeler tam kare, kırpma/birleştirme kopya. */
class FakeMockupVision implements MockupVisionContract
{
    /** @var array<int,float> sırayla dönecek ayak-ten oranları (boşsa 0 = ayakkabılı) */
    public static array $shoeSkin = [];

    public function segment(string $imagePath, string $labelsPath): array
    {
        file_put_contents($labelsPath, 'labels');

        return ['labels' => ['lower' => 100], 'checks' => ['shoe_skin_ratio' => array_shift(self::$shoeSkin) ?? 0.0]];
    }

    public function pair(string $leftPath, string $rightPath, string $outPath): void
    {
        copy($leftPath, $outPath);
    }

    public function mask(string $labelsPath, string $prefix, string $region, string $length): array
    {
        foreach (['_region', '_occluders', '_shoes'] as $s) {
            file_put_contents($prefix . $s . '.png', 'mask');
        }

        return ['crop' => [0, 0, 60, 80], 'long' => in_array($length, ['midi', 'maxi'], true)];
    }

    public function upscale(string $imagePath, string $outPath, int $width, int $height): void
    {
        copy($imagePath, $outPath);
    }

    public function crop(string $imagePath, array $box, string $outPath): void
    {
        copy($imagePath, $outPath);
    }

    public function composite(string $basePath, string $modelOutPath, string $prefix, array $box, string $resultPath, bool $restoreShoes): array
    {
        copy($modelOutPath, $resultPath);

        return ['align_mae' => 0.0, 'outside_unchanged' => 1.0, 'shoe_px_restored' => 0];
    }
}
