<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\MannequinRequest;

interface MannequinComposerContract
{
    /**
     * Tek bir sanal manken kimlik görseli üretir (tüm pozlara referans olur).
     *
     * @return string  Üretilen görselin yerel mutlak dosya yolu
     */
    public function compose(MannequinRequest $request): string;
}
