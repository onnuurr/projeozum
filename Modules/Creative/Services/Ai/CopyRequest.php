<?php

namespace Modules\Creative\Services\Ai;

/**
 * On-image pazarlama metni (headline/sub/cta) üretimi için girdi taşıyıcı (DTO).
 *
 * Ürün bağlamı + markanın "copy" kimliği (brief/tone/cta_phrases/banned_words) +
 * doldurulacak metin slotları (semantik anahtarlar) buradan CopyGeneratorContract'a
 * akar. extraInstructions, ret sonrası düzeltme talimatıdır: brief'in YERİNE geçmez,
 * ona EKLENİR (marka kimliği taban çizgisi olarak korunur).
 */
class CopyRequest
{
    /**
     * @param  string                $productName       Ürün adı
     * @param  string|null           $category          Ürün kategorisi (varsa)
     * @param  array<string,string>  $attributes        Ek ürün nitelikleri (materyal, cinsiyet vb.)
     * @param  array<int,string>     $slotKeys          Metin üretilecek slot anahtarları (headline, sub, cta ...)
     * @param  string                $brief             Marka yaratıcı brief'i (design_brief)
     * @param  string                $tone              Ton ipucu
     * @param  array<int,string>     $ctaPhrases        Tercih edilen CTA ifadeleri
     * @param  array<int,string>     $bannedWords       Üretilen metinde asla geçmemesi gereken kelimeler
     * @param  string|null           $aspectLabel       Hedef sosyal format oranı (ör. "9:16 vertical") — kısalık ipucu
     * @param  string|null           $extraInstructions Ret sonrası düzeltme talimatı (brief'e eklenir)
     */
    public function __construct(
        public string $productName,
        public ?string $category = null,
        public array $attributes = [],
        public array $slotKeys = [],
        public string $brief = '',
        public string $tone = '',
        public array $ctaPhrases = [],
        public array $bannedWords = [],
        public ?string $aspectLabel = null,
        public ?string $extraInstructions = null,
    ) {}
}
