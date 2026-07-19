<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Passthrough dedektör: hiçbir tespit üretmez.
 *
 * Tespit kapalıyken (config('creative.garment_detection.enabled') === false),
 * driver='null' iken, ya da henüz fine-tune edilmiş bir ağırlık dosyası
 * yokken (bkz. CreativeServiceProvider binding'i, is_file() kontrolü)
 * bağlanır. Bu durumda parça bilgisi tamamen manuel etiketleme aracına
 * (Faz G.2) düşer — giydirme pipeline'ı hiç etkilenmez.
 */
class NullGarmentPartDetector implements GarmentPartDetectorContract
{
    public function detect(string $imagePath): array
    {
        return [];
    }

    public function modelVersion(): string
    {
        return 'null';
    }
}
