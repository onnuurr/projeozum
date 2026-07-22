<?php

namespace Modules\Creative\Services\Ai;

/**
 * Tam-AI post kompozisyonu (fal.ai Flux.2) için girdi taşıyıcı.
 *
 * SceneRequest/CopyRequest ile aynı desen: yalnızca prompt/istek kurgusu için
 * bir taşıyıcı, Eloquent sızdırmaz — proje içi input, DTO yasağı kapsamına
 * girmez.
 */
class CompositionRequest
{
    /**
     * @param  string  $productName            Ürün adı (prompt bağlamı)
     * @param  array<int,string>  $productImagePaths  Kompozisyona referans verilecek ürün görselleri (yerel yollar)
     * @param  string|null  $mannequinReferencePath  Kullanılacak manken referans görseli (varsa)
     * @param  array<string,string>  $palette   Marka renk token'ları
     * @param  string|null  $aspectLabel        Hedef sosyal format oranı (örn. "4:5 portrait")
     * @param  string|null  $headline           Görsele hatasız basılması istenen başlık metni
     * @param  string|null  $subHeadline        Alt başlık metni (varsa)
     * @param  string|null  $ctaButton          CTA buton metni (varsa)
     * @param  string|null  $designBrief        Marka brief'i (mood/kompozisyon ipucu)
     */
    public function __construct(
        public string $productName,
        public array $productImagePaths = [],
        public ?string $mannequinReferencePath = null,
        public array $palette = [],
        public ?string $aspectLabel = null,
        public ?string $headline = null,
        public ?string $subHeadline = null,
        public ?string $ctaButton = null,
        public ?string $designBrief = null,
    ) {}
}
