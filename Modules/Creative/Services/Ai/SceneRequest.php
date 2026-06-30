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
     * @param  string       $productName        Ürün adı (prompt bağlamı)
     * @param  string|null  $productImagePath   Birincil ürün görseli (try-on giysi referansı)
     * @param  array<int,string>  $productImagePaths  Compose'a verilecek tüm ürün görselleri (yerel yollar)
     * @param  array<string,string>  $palette   Brand renk token'ları (prompt ton ipucu)
     * @param  string|null  $aspectLabel        Hedef sosyal format oranı (örn. "9:16 vertical")
     * @param  string|null  $promptOverride     Verilirse otomatik prompt yerine bu kullanılır
     * @param  string|null  $pose               Manken duruşu yönergesi; null ise builder
     *                                          ürün adına göre kürate bir poz seçer
     */
    public function __construct(
        public string $productName,
        public ?string $productImagePath = null,
        public array $productImagePaths = [],
        public array $palette = [],
        public ?string $aspectLabel = null,
        public ?string $promptOverride = null,
        public ?string $pose = null,
    ) {}
}
