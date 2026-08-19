<?php

namespace Modules\Creative\Services\Vision;

interface ColorAuditorContract
{
    /**
     * Orijinal ürün rengiyle giydirme çıktısındaki giysi bölgesinin rengini
     * karşılaştırır (CIE76 Delta E). Giysi bölgesi, giydirmeden ÖNCEKİ manken
     * görseli (`$posedPath`) ile SONRAKİ görsel (`$outputPath`) arasındaki
     * piksel farkından (diff-mask) çıkarılır — ayrı bir segmentasyon modeli
     * kullanılmaz (bkz. ROADMAP.md Faz Q).
     *
     * @return array{delta_e:?float,mask_ratio:float,confidence:string}|null
     *         `null` = ölçülemedi (dosya okunamadı, sürücü kapalı/hata verdi).
     *         DTO YOK — CLAUDE.md kuralı, diğer denetim motorlarıyla (ör.
     *         {@see \Modules\Creative\Services\LayoutConstraintEngine}) aynı
     *         "plain array" konvansiyonu.
     */
    public function measure(string $originalGarmentPath, string $posedPath, string $outputPath): ?array;
}
