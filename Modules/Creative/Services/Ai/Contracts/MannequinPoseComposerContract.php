<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\MannequinPoseRequest;

interface MannequinPoseComposerContract
{
    /**
     * Manken kimliğini koruyarak tek bir poz görseli üretir.
     *
     * @return string  Üretilen görselin yerel mutlak dosya yolu
     */
    public function compose(MannequinPoseRequest $request): string;
}
