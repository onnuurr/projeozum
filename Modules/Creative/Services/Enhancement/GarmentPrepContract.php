<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Giydirme (try-on) öncesi ürün/detay görseli hazırlama sözleşmesi.
 *
 * Yüklenen ham fotoğrafı (EXIF döndürme, gereksiz düz arka plan boşluğu, aşırı
 * boyut) AI'ya gitmeden önce temizler. Girdiyi DEĞİŞTİRMEZ; hazırlık devre
 * dışıysa veya başarısız olursa girdinin yolunu aynen döndürebilir (graceful
 * degrade) — bu adım asla giydirme pipeline'ını bozmamalıdır.
 */
interface GarmentPrepContract
{
    /**
     * @param  string  $sourcePath  Hazırlanacak görselin mutlak yolu.
     * @return string  Hazırlanmış görselin yolu (ya yeni temp, ya da $sourcePath).
     */
    public function prepare(string $sourcePath): string;
}
