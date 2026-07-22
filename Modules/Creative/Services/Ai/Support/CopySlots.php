<?php

namespace Modules\Creative\Services\Ai\Support;

/**
 * Bir metin slotunun semantik rolünü (headline / subheadline / cta / body)
 * anahtar adından çözer. Hem CopyService (hangi slotlara AI metni üretilecek)
 * hem MockCopyGenerator (role uygun şablon metin) bu haritayı kullanır.
 *
 * "product_name" bilinçli olarak KAPSAM DIŞIDIR — o slot ürün adının birebir
 * kendisiyle doldurulur (AI'a gitmez), bkz. CopyService.
 */
class CopySlots
{
    /**
     * rol => o role işaret eden anahtar parçaları. SIRA ÖNEMLİ: "subheadline"
     * anahtarı "headline" alt-dizesini, "subtitle" da "title"ı, "altbaslik" da
     * "baslik"ı içerdiği için subheadline rolü headline'dan ÖNCE denenmelidir.
     *
     * @var array<string,array<int,string>>
     */
    private const ROLE_HINTS = [
        'subheadline' => ['subheadline', 'subtitle', 'subhead', 'sub', 'tagline', 'slogan', 'altbaslik', 'altbaşlık'],
        'cta'         => ['cta', 'button', 'buton', 'action', 'aksiyon'],
        'body'        => ['body', 'description', 'aciklama', 'açıklama', 'promo', 'text', 'metin'],
        'headline'    => ['headline', 'title', 'heading', 'hero', 'baslik', 'başlık'],
    ];

    /**
     * Anahtarın rolü; AI metni üretilmeyecek bir anahtarsa null.
     */
    public static function roleFor(string $key): ?string
    {
        $normalized = mb_strtolower(trim($key));

        if ($normalized === '' || $normalized === 'product_name') {
            return null;
        }

        foreach (self::ROLE_HINTS as $role => $hints) {
            foreach ($hints as $hint) {
                if (str_contains($normalized, $hint)) {
                    return $role;
                }
            }
        }

        return null;
    }

    /**
     * AI pazarlama metni üretilebilir bir slot anahtarı mı?
     */
    public static function isCopyKey(string $key): bool
    {
        return self::roleFor($key) !== null;
    }
}
