<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\PoseRequest;

interface PosePreviewComposerContract
{
    /**
     * Nötr/jenerik bir figürü verilen duruşta üretir (poz kütüphanesi önizlemesi).
     *
     * @return string  Üretilen görselin yerel mutlak dosya yolu
     */
    public function compose(PoseRequest $request): string;
}
