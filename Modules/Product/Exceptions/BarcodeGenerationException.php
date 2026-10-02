<?php

namespace Modules\Product\Exceptions;

use RuntimeException;

/**
 * Barkod (EAN-13/GS1) otomatik üretimi başarısız olduğunda fırlatılır:
 *  - superadmin ayarlarındaki ülke/firma kodu + seri aralığı 12 haneye sığmıyorsa (misconfigured),
 *  - ayarlardaki seri aralığında denemelerde boş (kullanılmamış) numara bulunamadıysa (rangeExhausted).
 *
 * ProductService::create() bu istisnayı ürün oluşturma sırasında sessizce yutar (barkod boş kalır);
 * ProductBarcodeController ise "Yeniden Üret" isteğinde bunu doğrudan kullanıcıya hata olarak döner.
 */
class BarcodeGenerationException extends RuntimeException
{
    public static function misconfigured(string $reason): self
    {
        return new self(__('product::messages.barcode_misconfigured', ['reason' => $reason]));
    }

    public static function rangeExhausted(int $min, int $max): self
    {
        return new self(__('product::messages.barcode_range_exhausted', ['min' => $min, 'max' => $max]));
    }
}
