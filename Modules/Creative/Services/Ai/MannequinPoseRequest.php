<?php

namespace Modules\Creative\Services\Ai;

/**
 * Bir manken pozu üretimi için girdi taşıyıcı (DTO).
 *
 * Compose aşaması, manken kimlik referans görselini (referenceImagePath) Gemini'ye
 * referans olarak verir ve yalnızca duruşu (directive) değiştirir; kimlik korunur.
 */
class MannequinPoseRequest
{
    /**
     * @param  string       $referenceImagePath  Manken kimlik referansının yerel mutlak yolu
     * @param  string       $label               Poz etiketi (bağlam)
     * @param  string       $directive           Duruş yönergesi (config kataloğundan)
     * @param  string|null  $posePreviewPath     Pozun önizleme görselinin yerel yolu (birebir poz referansı)
     * @param  string|null  $promptOverride      Verilirse otomatik prompt yerine bu kullanılır
     */
    public function __construct(
        public string $referenceImagePath,
        public string $label,
        public string $directive,
        public ?string $posePreviewPath = null,
        public ?string $promptOverride = null,
    ) {}
}
