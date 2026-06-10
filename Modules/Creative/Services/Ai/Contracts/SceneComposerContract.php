<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\SceneRequest;

interface SceneComposerContract
{
    /**
     * Bir model/sahne görseli kurgular (karakter + poz + sahne).
     *
     * @return string  Üretilen görselin yerel mutlak dosya yolu
     */
    public function compose(SceneRequest $request): string;
}
