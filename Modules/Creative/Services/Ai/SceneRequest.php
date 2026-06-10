<?php

namespace Modules\Creative\Services\Ai;

/**
 * AI sahne kurgusu için girdi taşıyıcı (DTO).
 *
 * Compose aşaması (Gemini) bunu prompt üretmek için kullanır; try-on aşaması
 * ürün görselini giysi olarak alır.
 */
class SceneRequest
{
    /**
     * @param  string       $productName       Ürün adı (prompt bağlamı)
     * @param  string|null  $productImagePath  Ürünün yerel görsel yolu (giysi/ürün referansı)
     * @param  array<string,string>  $palette  Brand renk token'ları (prompt ton ipucu)
     * @param  string|null  $promptOverride    Verilirse otomatik prompt yerine bu kullanılır
     */
    public function __construct(
        public string $productName,
        public ?string $productImagePath = null,
        public array $palette = [],
        public ?string $promptOverride = null,
    ) {}
}
