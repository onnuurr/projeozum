<?php

namespace Modules\Creative\Services;

use Modules\Creative\Models\PartnerBrandKit;
use Modules\Creative\Services\Ai\Support\CreativeSpec;

/**
 * BrandKit + (opsiyonel) PartnerBrandKit + kanal + format'tan bir
 * CreativeSpec üreten SAF kural katmanı. Hiçbir AI çağrısı YAPMAZ, hiçbir
 * DB sorgusu YAPMAZ — girdi tamamen çağıranın (CreativeRenderService)
 * hazırladığı veridir. Aynı desen: bkz. GarmentIdentityRuleEngine.
 *
 * Kanal ayrımı (kullanıcı mimarisi):
 * - brand_post: markanın kendi paylaşımı — logo zorunlu, partner logosu yok.
 * - partner_share: iş ortağının kendi hesabında paylaşacağı görsel — marka
 *   logosu YANINDA partner logosu da zorunlu, partner'a özel fiyat/CTA
 *   PartnerBrandKit'ten gelir. BrandKit'in temel kuralları (renk/ton) BOZULMAZ.
 * - campaign: kampanya görseli — brand_post'a yakın ama dekoratif öğelere
 *   daha açık (kampanya rozeti/countdown gibi görsel vurgular).
 */
class CreativeRulesEngine
{
    /**
     * @param  array{palette:array<string,string>,criteria:array{design_brief:?string,tone:?string,banned_words:string[]}}  $brandTokens  BrandTokenService::tokens()/tokensFor() çıktısı
     */
    public function buildSpec(
        array $brandTokens,
        string $channel,
        string $formatKey,
        ?PartnerBrandKit $partner = null,
    ): CreativeSpec {
        $channel = in_array($channel, [
            CreativeSpec::CHANNEL_BRAND_POST,
            CreativeSpec::CHANNEL_PARTNER_SHARE,
            CreativeSpec::CHANNEL_CAMPAIGN,
        ], true) ? $channel : CreativeSpec::CHANNEL_BRAND_POST;

        $isPartner = $channel === CreativeSpec::CHANNEL_PARTNER_SHARE;
        $isCampaign = $channel === CreativeSpec::CHANNEL_CAMPAIGN;

        $bannedWords = (array) ($brandTokens['criteria']['banned_words'] ?? []);

        return new CreativeSpec(
            channel: $channel,
            format: $formatKey,
            visualStyle: $this->visualStyleSlug($brandTokens['criteria']['tone'] ?? null),
            background: $isCampaign ? 'campaign_themed' : 'brand_compatible',
            // Marka logosu her kanalda zorunlu — partner_share'de dahi markanın
            // kendi logosu kalkmaz, partner logosu buna EK olur (kullanıcı notu:
            // "BrandKit'in ana kuralları bozulmamalı").
            logoRequired: true,
            logoSafeArea: true,
            partnerLogoRequired: $isPartner && $partner !== null,
            productDominant: true,
            preserveProductIdentity: true,
            // Kampanyada rozet/countdown gibi ek dekoratif öğelere biraz daha
            // açık; partner paylaşımında iletişim/fiyat bloğuna yer açmak için
            // dekoratif öğe sayısı kısıtlı tutulur.
            maxDecorativeElements: $isCampaign ? 3 : ($isPartner ? 1 : 2),
            bannedWords: $bannedWords,
        );
    }

    /**
     * BrandKit.tone (serbest metin, ör. "Premium / Minimal") → slug.
     * Eşleşme yoksa güvenli varsayılana ("clean_modern") düşer.
     */
    private function visualStyleSlug(?string $tone): string
    {
        $tone = mb_strtolower(trim((string) $tone));

        if ($tone === '') {
            return 'clean_modern';
        }

        $slug = preg_replace('/[^a-z0-9]+/u', '_', $tone);
        $slug = trim((string) $slug, '_');

        return $slug !== '' ? $slug : 'clean_modern';
    }
}
