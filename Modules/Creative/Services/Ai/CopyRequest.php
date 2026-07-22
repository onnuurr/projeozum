<?php

namespace Modules\Creative\Services\Ai;

/**
 * Görsel-üstü pazarlama metni (headline/sub-headline/CTA) üretimi için girdi
 * taşıyıcı (DTO). Marka kriterleri (brief/ton/CTA listesi/yasaklı kelimeler)
 * BrandTokenService'in `criteria` çıktısından buraya akar.
 */
class CopyRequest
{
    /**
     * @param  string                $productName    Ürün adı
     * @param  string|null           $category       Ürün kategorisi (varsa)
     * @param  array<string,string>  $attributes     Ek ürün nitelikleri (materyal, cinsiyet vb.)
     * @param  string|null           $designBrief    Marka brief'i (admin tanımlı, MUST follow)
     * @param  string|null           $tone           Marka tonu (ör. samimi, premium)
     * @param  array<int,string>     $ctaPhrases     CTA kapalı listesi (boşsa AI serbest üretir)
     * @param  array<int,string>     $bannedWords    Yasaklı kelimeler (metinde asla geçmemeli)
     */
    public function __construct(
        public string $productName,
        public ?string $category = null,
        public array $attributes = [],
        public ?string $designBrief = null,
        public ?string $tone = null,
        public array $ctaPhrases = [],
        public array $bannedWords = [],
    ) {}
}
