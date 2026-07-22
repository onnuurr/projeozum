<?php

namespace Modules\Creative\Services\Ai\Support;

/**
 * Üretilen pazarlama metni üzerinde markanın "yasaklı kelime" güvencesini KOD
 * seviyesinde uygular — LLM talimatı yok sayarsa bile marka güvenliği korunur
 * (aynı felsefe: OCR doğrulaması / GarmentIdentityRuleEngine gibi, garantiyi
 * modele değil koda gömmek). Saf/yan-etkisiz; DB/AI çağrısı yapmaz.
 */
class CopyConstraints
{
    /**
     * Her metin değerinden yasaklı kelimeleri (tam kelime, büyük/küçük harf
     * duyarsız, Unicode/Türkçe uyumlu) ayıklar; artan boşlukları toparlar.
     * Ayıklama sonrası boşalan bir slot dönüşten tamamen düşürülür (yarım/
     * bozuk metin render etmektense hiç metin yazmamak yeğ).
     *
     * @param  array<string,string>  $copy
     * @param  array<int,string>     $bannedWords
     * @return array<string,string>
     */
    public static function sanitize(array $copy, array $bannedWords): array
    {
        $banned = array_values(array_filter(array_map('trim', $bannedWords), fn ($w) => $w !== ''));
        if ($banned === []) {
            return array_filter($copy, fn ($v) => is_string($v) && trim($v) !== '');
        }

        $out = [];
        foreach ($copy as $key => $text) {
            if (! is_string($text)) {
                continue;
            }

            $clean = self::strip($text, $banned);
            if ($clean !== '') {
                $out[$key] = $clean;
            }
        }

        return $out;
    }

    /**
     * Tek bir metinden yasaklı kelimeleri çıkarır ve boşlukları normalize eder.
     *
     * @param  array<int,string>  $banned
     */
    public static function strip(string $text, array $banned): string
    {
        foreach ($banned as $word) {
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu';
            $text    = (string) preg_replace($pattern, '', $text);
        }

        // Yasaklı kelime çıkınca oluşan çift boşluk / boşluk-önce-noktalama toparlanır.
        $text = (string) preg_replace('/\s{2,}/u', ' ', $text);
        $text = (string) preg_replace('/\s+([,.!?;:])/u', '$1', $text);

        return trim($text);
    }

    /**
     * Metin bir yasaklı kelime içeriyor mu? (test/doğrulama için)
     *
     * @param  array<int,string>  $banned
     */
    public static function containsBanned(string $text, array $banned): bool
    {
        foreach ($banned as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . '(?![\p{L}\p{N}])/iu', $text)) {
                return true;
            }
        }

        return false;
    }
}
