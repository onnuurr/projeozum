<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\CloseupRequest;

interface CloseupComposerContract
{
    /**
     * Tam boy giydirmeden aynı çekimin yakın plan / detay karesini üretir.
     *
     * @return string  Üretilen görselin yerel mutlak dosya yolu
     */
    public function compose(CloseupRequest $request): string;

    /** Üretimde kullanılan model adı (raporlama: tryon_model kolonu). */
    public function modelIdentifier(): string;
}
