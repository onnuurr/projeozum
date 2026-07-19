<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Passthrough sınıflandırıcı: hiçbir öneri üretmez.
 *
 * Sınıflandırma kapalıyken (config('creative.detail_classification.enabled') === false)
 * veya driver='null' iken bağlanır. Dev/test ortamında torch/open_clip aranmaması için de uygundur.
 */
class NullGarmentDetailClassifier implements GarmentDetailClassifierContract
{
    public function classify(array $imagePaths): array
    {
        return array_fill_keys($imagePaths, []);
    }
}
