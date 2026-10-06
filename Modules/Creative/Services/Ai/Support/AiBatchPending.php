<?php

namespace Modules\Creative\Services\Ai\Support;

use RuntimeException;

/**
 * Batch modunda görsel çağrısının sonucu henüz yok (kuyrukta / Google'da işleniyor).
 * Yalnız AiBatchService::defer() içinde fırlar ve orada yakalanır; çağıran null görür.
 */
class AiBatchPending extends RuntimeException
{
    public function __construct(public readonly string $key)
    {
        parent::__construct("AI batch sonucu bekleniyor: {$key}");
    }
}
