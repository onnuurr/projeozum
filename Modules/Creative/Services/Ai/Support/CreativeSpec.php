<?php

namespace Modules\Creative\Services\Ai\Support;

/**
 * Creative Rules Engine'in ürettiği, Prompt Builder'a giden yapılandırılmış
 * kreatif spesifikasyon. Rules Engine → Creative Specification → Prompt
 * Builder → AI zincirinin ortasındaki sabit sözleşme.
 *
 * KRİTİK mimari kural: bu spesifikasyon AI'a yalnızca atmosfer/arka plan/
 * kompozisyon/dekoratif öğe ürettirir — logo, fiyat, telefon, web adresi,
 * kampanya tarihi, ürün adı, SKU, iş ortağı bilgileri gibi KESİN metinleri
 * AI'a hiç çizdirmez (AI bunları hatalı/bozuk üretebilir, ör. "1.299 TL"
 * yerine "1.299 T1"). O öğeler mevcut SVG şablon render motoru (kesin
 * font/renk pipeline'ı) tarafından AYRICA, spec'ten bağımsız olarak
 * yerleştirilir — bkz. CreativeRenderService::generate()'in şablon render
 * adımı, bu spec'ten SONRA çalışır ve değişmedi.
 */
class CreativeSpec
{
    public const CHANNEL_BRAND_POST    = 'brand_post';
    public const CHANNEL_PARTNER_SHARE = 'partner_share';
    public const CHANNEL_CAMPAIGN      = 'campaign';

    /**
     * @param  string  $channel        brand_post | partner_share | campaign
     * @param  string  $format         config('creative.formats') anahtarı (ör. instagram_story)
     * @param  string  $visualStyle    BrandKit.tone'dan türetilmiş slug (ör. premium_minimal)
     * @param  string  $background     brand_compatible | campaign_themed
     * @param  bool    $logoRequired
     * @param  bool    $logoSafeArea   Logo alanı AI kompozisyonunda boş/temiz bırakılmalı
     * @param  bool    $partnerLogoRequired  Yalnız partner_share kanalında true
     * @param  bool    $productDominant
     * @param  bool    $preserveProductIdentity
     * @param  int     $maxDecorativeElements
     * @param  array<int,string>  $bannedWords  BrandKit.banned_words + partner kısıtları
     */
    public function __construct(
        public string $channel,
        public string $format,
        public string $visualStyle,
        public string $background,
        public bool $logoRequired,
        public bool $logoSafeArea,
        public bool $partnerLogoRequired,
        public bool $productDominant,
        public bool $preserveProductIdentity,
        public int $maxDecorativeElements,
        public array $bannedWords = [],
    ) {}

    /**
     * Prompt Builder'ın okuyacağı düz dizi — ayrıca meta/debug amaçlı
     * CreativeAsset.meta'ya da olduğu gibi yazılır (bkz. CreativeRenderService).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'channel'      => $this->channel,
            'format'       => $this->format,
            'visual_style' => $this->visualStyle,
            'background'   => $this->background,
            'logo'         => [
                'required'  => $this->logoRequired,
                'safe_area' => $this->logoSafeArea,
            ],
            'partner_logo_required' => $this->partnerLogoRequired,
            'product'      => [
                'dominant'          => $this->productDominant,
                'preserve_identity' => $this->preserveProductIdentity,
            ],
            'decorative_elements' => [
                'allowed'   => $this->maxDecorativeElements > 0,
                'max_count' => $this->maxDecorativeElements,
            ],
            'banned_words' => $this->bannedWords,
        ];
    }
}
