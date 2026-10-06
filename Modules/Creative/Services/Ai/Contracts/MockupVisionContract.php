<?php

namespace Modules\Creative\Services\Ai\Contracts;

/**
 * Mokap giydirmenin görüntü işleme adımları (segmentasyon, maske, kırpma, birleştirme).
 * Üretim sürücüsü: PythonMockupVision (Modules/Creative/python/mockup_*.py, Apache 2.0 bileşenler).
 */
interface MockupVisionContract
{
    /**
     * Görseli ayrıştırır, etiket haritasını $labelsPath'e (.npy) yazar.
     *
     * @return array<string,mixed>  {labels: {sınıf: piksel}, landmarks: {...}}
     */
    public function segment(string $imagePath, string $labelsPath): array;

    /**
     * Bölge/önde kalan/ayakkabı maskelerini "<önek>_region.png" vb. olarak yazar.
     *
     * @return array{crop: array{0:int,1:int,2:int,3:int}, long: bool}
     */
    public function mask(string $labelsPath, string $prefix, string $region, string $length): array;

    /** İki görseli yan yana tek görsele koyar (otomatik kontroller için; ücretsiz). */
    public function pair(string $leftPath, string $rightPath, string $outPath): void;

    /** Görseli tam (genişlik × yükseklik) boyuta büyütür (FSRCNN, ücretsiz). */
    public function upscale(string $imagePath, string $outPath, int $width, int $height): void;

    /** @param array{0:int,1:int,2:int,3:int} $box */
    public function crop(string $imagePath, array $box, string $outPath): void;

    /**
     * Model çıktısını baz görselle birleştirip $resultPath'e yazar.
     *
     * @param  array{0:int,1:int,2:int,3:int}  $box
     * @return array{align_mae: float, outside_unchanged: float, shoe_px_restored: int}
     */
    public function composite(string $basePath, string $modelOutPath, string $prefix, array $box, string $resultPath, bool $restoreShoes): array;
}
