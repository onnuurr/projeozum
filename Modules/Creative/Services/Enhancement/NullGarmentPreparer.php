<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Passthrough garment preparer: girdi yolunu aynen döndürür.
 *
 * Hazırlık kapalıyken (config('creative.garment_prep.enabled') === false) veya
 * driver='null' iken bağlanır. Dev/test ortamında Pillow aranmaması için de uygundur.
 */
class NullGarmentPreparer implements GarmentPrepContract
{
    public function prepare(string $sourcePath): string
    {
        return $sourcePath;
    }
}
