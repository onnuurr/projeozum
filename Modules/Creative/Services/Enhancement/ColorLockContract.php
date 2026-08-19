<?php

namespace Modules\Creative\Services\Enhancement;

interface ColorLockContract
{
    /**
     * Giydirme çıktısındaki giysi bölgesinin rengini orijinal ürün fotoğrafına
     * doğru kaydırır (LAB uzayında yalnız a/b kanalları — L/parlaklık, yani
     * modelden gelen gölge/kırışıklık/kumaş dokusu KORUNUR). Giysi bölgesi
     * `$posedPath` (giydirmeden önce) ile `$outputPath` (giydirmeden sonra)
     * arasındaki piksel farkından bulunur (bkz. ROADMAP.md Faz Q).
     *
     * Maske düşük güvenliyse (bulunamadı, çok küçük/büyük) `$outputPath` AYNEN
     * döner — bu adım hiçbir zaman görseli bozma riski almaz.
     *
     * @return string  Düzeltilmiş (ya da değişmemiş) görselin yerel mutlak yolu.
     */
    public function apply(string $outputPath, string $originalGarmentPath, string $posedPath): string;
}
