<?php

namespace Modules\Creative\Services\Vision;

/**
 * `creative.color_fidelity.audit.enabled=false` iken (varsayılan) bağlanan
 * pasif sürücü — her zaman `null` döner, hiçbir Python çağrısı yapmaz.
 */
class NullColorAuditor implements ColorAuditorContract
{
    public function measure(string $originalGarmentPath, string $posedPath, string $outputPath): ?array
    {
        return null;
    }
}
