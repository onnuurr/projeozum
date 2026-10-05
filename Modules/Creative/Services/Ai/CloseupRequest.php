<?php

namespace Modules\Creative\Services\Ai;

/**
 * Yakın plan / detay çekimi üretimi için girdi taşıyıcı.
 *
 * Kaynak, onaylı tam boy giydirme görselidir (sahne/ışık/manken/kombin); giysi
 * görselleri ürünün orijinal fotoğrafı + varsa detay görselleridir ve yakın
 * kadrajda desen/doku/dikişin uydurulmaması için sadakat referansı olarak verilir.
 */
class CloseupRequest
{
    /**
     * @param  string  $sourceImagePath   Onaylı tam boy giydirmenin yerel mutlak yolu
     * @param  string  $garmentImagePath  Ürünün ana fotoğrafının yerel mutlak yolu
     * @param  string  $shotPrompt        Kadraj tarifi (config creative.closeup.shots.*.prompt)
     * @param  array<int,array{path:string,label:?string}>  $extraGarmentImages  Ek detay görselleri
     * @param  string|null  $extraInstruction  Ret sonrası sohbetten gelen düzeltme talimatı
     */
    public function __construct(
        public string $sourceImagePath,
        public string $garmentImagePath,
        public string $shotPrompt,
        public array $extraGarmentImages = [],
        public ?string $extraInstruction = null,
    ) {}
}
