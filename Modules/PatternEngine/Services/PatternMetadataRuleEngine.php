<?php

namespace Modules\Atelier\Services;

/**
 * Confidence eşiği altındaki AI çıkarım alanlarını eleyen SAF iş kuralı
 * katmanı (PİLOT). Hiçbir AI çağrısı YAPMAZ, hiçbir DB sorgusu YAPMAZ — girdi
 * tamamen {@see \Modules\Atelier\Services\Ai\Contracts\PatternMetadataExtractorContract}'ın
 * ürettiği versioned zarftır. Ham 'data' ASLA doğrudan Pattern kolonlarına
 * yazılmaz; her zaman bu katmandan geçer (bkz. Creative modülünün
 * {@see \Modules\Creative\Services\GarmentIdentityRuleEngine} ile aynı desen).
 */
class PatternMetadataRuleEngine
{
    // "OTHER" + raw_text yalnızca KISA bir ürün tipi adı için (ör. "tulum",
    // "denim ceket") — bu kolon kısa bir kategori etiketidir, gerekçe cümlesi
    // DEĞİL. Gerçek vaka: kapak sayfasında ürün fotoğrafı olmayan bir Ruslan
    // karo sayfasında Gemini "OTHER" + raw_text="The image shows a single
    // pattern piece... cannot be determined from this page alone." (yüksek
    // confidence'la) döndürdü — sınır olmadan bu paragraf doğrudan product_type
    // kolonuna yazılıyordu. Bu eşiği aşan raw_text "belirlenemedi" sayılır (null).
    private const MAX_RAW_TEXT_LEN = 40;

    /**
     * @param  array{data:array{product_type:array{value:?string,raw_text:?string,confidence:float},size_range:array{value:?string,confidence:float}}}  $envelope
     * @return array{product_type:?string,size_range:?string}
     */
    public function decide(array $envelope): array
    {
        $threshold = (float) config('atelier.pattern_metadata_ai.confidence_threshold', 0.6);
        $data = $envelope['data'] ?? [];

        return [
            'product_type' => $this->confidentValue($data['product_type'] ?? null, $threshold),
            'size_range'   => $this->confidentValue($data['size_range'] ?? null, $threshold, withRawText: false),
        ];
    }

    /**
     * @param  array{value:?string,raw_text?:?string,confidence:float}|null  $field
     */
    private function confidentValue(?array $field, float $threshold, bool $withRawText = true): ?string
    {
        if (! is_array($field) || (float) ($field['confidence'] ?? 0) < $threshold) {
            return null;
        }

        $value = $field['value'] ?? null;
        if ($withRawText && $value === 'OTHER') {
            $rawText = is_string($field['raw_text'] ?? null) ? trim($field['raw_text']) : null;
            $value = ($rawText !== null && $rawText !== '' && mb_strlen($rawText) <= self::MAX_RAW_TEXT_LEN)
                ? $rawText
                : null;
        }

        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}
