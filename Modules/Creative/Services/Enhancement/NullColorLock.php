<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * `creative.color_fidelity.lock.enabled=false` iken (varsayılan) bağlanan
 * pasif sürücü — girdi yolunu aynen döner (passthrough), hiçbir Python
 * çağrısı yapmaz. {@see NullGarmentPreparer} ile aynı desen.
 */
class NullColorLock implements ColorLockContract
{
    public function apply(string $outputPath, string $originalGarmentPath, string $posedPath): string
    {
        return $outputPath;
    }
}
