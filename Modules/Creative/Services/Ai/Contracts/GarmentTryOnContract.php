<?php

namespace Modules\Creative\Services\Ai\Contracts;

interface GarmentTryOnContract
{
    /**
     * Ürünü (giysiyi) model görseline giydirir.
     *
     * @param  string  $modelImagePath    Model/sahne görselinin yerel yolu (compose çıktısı)
     * @param  string  $garmentImagePath  Ürün/giysi görselinin yerel yolu
     * @return string  Giydirilmiş sonucun yerel mutlak dosya yolu
     */
    public function tryOn(string $modelImagePath, string $garmentImagePath): string;
}
