<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Passthrough enhancer: girdi yolunu aynen döndürür.
 *
 * İyileştirme kapalıyken (config('creative.enhance.enabled') === false) veya
 * driver='null' iken bağlanır. Dev/test ortamında opencv aranmaması için de uygundur.
 */
class NullImageEnhancer implements ImageEnhancerContract
{
    public function enhance(string $sourcePath): string
    {
        return $sourcePath;
    }
}
