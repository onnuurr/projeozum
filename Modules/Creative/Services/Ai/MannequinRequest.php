<?php

namespace Modules\Creative\Services\Ai;

/**
 * Sanal manken üretimi için girdi taşıyıcı (DTO).
 *
 * Compose aşaması (Gemini) bunu kimlik prompt'u üretmek için kullanır; çıktı
 * tüm pozlara referans olacak tek bir manken görselidir.
 */
class MannequinRequest
{
    /**
     * @param  string       $name            Manken adı (bağlam/etiket)
     * @param  string|null  $gender          Cinsiyet ipucu (örn. "female")
     * @param  string|null  $ageRange        Yaş aralığı (örn. "25-30")
     * @param  string|null  $skinTone        Ten tonu (örn. "medium")
     * @param  string|null  $bodyType        Vücut tipi (örn. "athletic")
     * @param  string|null  $hair            Saç tarifi (örn. "uzun düz kahverengi")
     * @param  string|null  $face            Yüz tarifi (kimliğin en belirleyici parçası)
     * @param  int|null     $heightCm        Boy (cm)
     * @param  int|null     $bustCm          Göğüs çevresi (cm)
     * @param  int|null     $waistCm         Bel çevresi (cm)
     * @param  int|null     $hipsCm          Kalça çevresi (cm)
     * @param  string|null  $extras          Serbest ek tarif (operatör notu)
     * @param  string|null  $promptOverride  Verilirse otomatik prompt yerine bu kullanılır
     */
    public function __construct(
        public string $name,
        public ?string $gender = null,
        public ?string $ageRange = null,
        public ?string $skinTone = null,
        public ?string $bodyType = null,
        public ?string $hair = null,
        public ?string $face = null,
        public ?int $heightCm = null,
        public ?int $bustCm = null,
        public ?int $waistCm = null,
        public ?int $hipsCm = null,
        public ?string $extras = null,
        public ?string $promptOverride = null,
    ) {}
}
