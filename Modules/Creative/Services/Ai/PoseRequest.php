<?php

namespace Modules\Creative\Services\Ai;

/**
 * Bağımsız poz önizmesi üretimi için girdi taşıyıcı (DTO).
 *
 * Mankenden bağımsızdır: nötr/jenerik bir figür verilen duruşta üretilir.
 */
class PoseRequest
{
    /**
     * @param  string       $label           Poz etiketi (bağlam)
     * @param  string       $directive       Duruş yönergesi
     * @param  string|null  $promptOverride  Verilirse otomatik prompt yerine bu kullanılır
     */
    public function __construct(
        public string $label,
        public string $directive,
        public ?string $promptOverride = null,
    ) {}
}
