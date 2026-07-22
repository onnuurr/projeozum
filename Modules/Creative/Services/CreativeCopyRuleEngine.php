<?php

namespace Modules\Creative\Services;

/**
 * AI'nin ürettiği görsel-üstü metni (headline/sub-headline/CTA) marka
 * kriterlerine göre denetleyen SAF iş kuralı katmanı. Hiçbir AI çağrısı
 * YAPMAZ, hiçbir DB sorgusu YAPMAZ — aynı disiplin {@see GarmentIdentityRuleEngine}
 * ile. Ham `copy` zarfı ASLA doğrudan render'a yazılmaz; her zaman bu
 * katmandan geçer.
 *
 * Marka onaysız CTA metni asla üretime girmez: CTA kapalı bir liste
 * tanımlıysa (BrandKit.cta_phrases) ve AI'nin ürettiği değer bu listede
 * yoksa, buton boş kalmasın diye listenin ilk ifadesine coerce edilir —
 * silinmez (bilinçli tercih).
 */
class CreativeCopyRuleEngine
{
    private const FIELD_TO_DATA_KEY = [
        'headline'     => 'headline',
        'sub_headline' => 'sub_headline',
        'cta_button'   => 'cta',
    ];

    /**
     * @param  array<string,array{value:?string,confidence:float}>  $data       Copy envelope'unun `data` alanı
     * @param  array{cta_phrases?:array<int,string>,banned_words?:array<int,string>}  $criteria   BrandTokenService criteria
     * @param  array<string,array<string,mixed>>  $slotsByKey  Şablonun headline/sub_headline/cta_button slot tanımları
     * @return array{headline:?string,sub_headline:?string,cta_button:?string}
     */
    public function resolve(array $data, array $criteria, array $slotsByKey): array
    {
        $threshold   = (float) config('creative.ai.copy.confidence_threshold', 0.6);
        $ctaPhrases  = $this->stringList($criteria['cta_phrases'] ?? []);
        $bannedWords = $this->stringList($criteria['banned_words'] ?? []);

        $out = ['headline' => null, 'sub_headline' => null, 'cta_button' => null];

        foreach (self::FIELD_TO_DATA_KEY as $slotKey => $dataKey) {
            $field      = is_array($data[$dataKey] ?? null) ? $data[$dataKey] : [];
            $value      = isset($field['value']) ? trim((string) $field['value']) : '';
            $confidence = isset($field['confidence']) ? (float) $field['confidence'] : 0.0;

            // 1) Confidence gate — emin olunmayan bir tahmin asla üretime girmez.
            if ($value === '' || $confidence < $threshold) {
                continue;
            }

            // 2) CTA kapalı-sözlük denetimi (coerce, drop değil).
            if ($slotKey === 'cta_button' && $ctaPhrases !== []) {
                $value = $this->normalizeCta($value, $ctaPhrases);
            }

            // 3) Yasaklı kelime taraması — eşleşirse TÜM alan düşer (kısmi redaksiyon yok).
            if ($this->containsBannedWord($value, $bannedWords)) {
                continue;
            }

            // 4) Slot genişliğine göre karakter tavanı.
            $value = $this->applyWidthCap($value, $slotsByKey[$slotKey] ?? null);

            $out[$slotKey] = $value;
        }

        return $out;
    }

    /**
     * @param  array<int,string>  $ctaPhrases
     */
    private function normalizeCta(string $value, array $ctaPhrases): string
    {
        foreach ($ctaPhrases as $phrase) {
            if (mb_strtolower(trim($phrase)) === mb_strtolower($value)) {
                return trim($phrase); // whitelist'in kanonik yazımı korunur
            }
        }

        return trim($ctaPhrases[0]);
    }

    /**
     * @param  array<int,string>  $bannedWords
     */
    private function containsBannedWord(string $value, array $bannedWords): bool
    {
        $lower = mb_strtolower($value);

        foreach ($bannedWords as $word) {
            $word = mb_strtolower(trim($word));
            if ($word !== '' && str_contains($lower, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kaba bir karakter-genişliği tahminiyle metni kırpar (gerçek font metriği
     * değil — Pillow/resvg font-metrik ölçümü daha ağır bir sonraki adım,
     * bilinçli bir maliyet/doğruluk ödünleşimi).
     *
     * @param  array<string,mixed>|null  $slot
     */
    private function applyWidthCap(string $value, ?array $slot): string
    {
        $w        = (float) ($slot['w'] ?? 0);
        $fontSize = (float) ($slot['font_size'] ?? 0);

        if ($w <= 0 || $fontSize <= 0) {
            return $value;
        }

        $glyphWidthFactor = (float) config('creative.ai.copy.glyph_width_factor', 0.55);
        $maxChars         = (int) floor($w / ($fontSize * $glyphWidthFactor));

        if ($maxChars <= 0 || mb_strlen($value) <= $maxChars) {
            return $value;
        }

        return mb_substr($value, 0, max(0, $maxChars - 1)) . '…';
    }

    /**
     * @return array<int,string>
     */
    private function stringList(mixed $list): array
    {
        $out = [];
        foreach ((array) ($list ?? []) as $value) {
            if (is_string($value) && trim($value) !== '') {
                $out[] = trim($value);
            }
        }

        return array_values($out);
    }
}
