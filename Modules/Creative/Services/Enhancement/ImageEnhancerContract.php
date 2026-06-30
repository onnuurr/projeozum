<?php

namespace Modules\Creative\Services\Enhancement;

/**
 * Üretim sonrası görsel kalite iyileştirme sözleşmesi.
 *
 * Ham AI/ render çıktısını alıp çözünürlük/keskinlik açısından iyileştirilmiş
 * yeni bir geçici dosya üretir. Girdiyi DEĞİŞTİRMEZ; iyileştirme devre dışıysa
 * veya başarısız olursa girdinin yolunu aynen döndürebilir (graceful degrade).
 */
interface ImageEnhancerContract
{
    /**
     * @param  string  $sourcePath  İyileştirilecek görselin mutlak yolu.
     * @return string  İyileştirilmiş görselin yolu (ya yeni temp, ya da $sourcePath).
     */
    public function enhance(string $sourcePath): string;
}
