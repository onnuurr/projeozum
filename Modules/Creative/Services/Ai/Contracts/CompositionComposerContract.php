<?php

namespace Modules\Creative\Services\Ai\Contracts;

use Modules\Creative\Services\Ai\CompositionRequest;

interface CompositionComposerContract
{
    /**
     * Tam-AI post kompozisyonu (ürün + manken + sahne + metin) üretir.
     *
     * @return string  Üretilen görselin yerel mutlak dosya yolu
     */
    public function compose(CompositionRequest $request): string;

    /**
     * Bu sürücünün o anki config'e göre çalıştırdığı modelin tanımlayıcısı
     * (ör. "fal-ai/flux-2", "mock") — CreativeAsset.meta.composition_model'e
     * kaydedilir (bkz. GarmentTryOnContract::modelIdentifier presedansı).
     */
    public function modelIdentifier(): string;
}
