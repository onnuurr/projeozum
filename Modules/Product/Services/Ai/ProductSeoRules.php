<?php

namespace Modules\Product\Services\Ai;

/**
 * AI'nın ürettiği SEO metinleri ile forma giden değer arasındaki deterministik kural katmanı (AI'sız,
 * birim test edilebilir). Model uzunluk/biçim talimatına her zaman uymaz; burada zorlanır:
 *  - meta başlık ≤ 60, meta açıklama ≤ 160 karakter (kelime sınırında kesilir), markdown/tırnak temizlenir;
 *  - anahtar kelimeler: virgülle ayrılır, Türkçe küçük harfe çevrilir, tekrarlar ve 40 karakterden
 *    uzunlar atılır, en fazla 10 tane, alan sınırı (255) aşılmaz.
 * Boş kalan alan null döner (form o alanı değiştirmez).
 */
class ProductSeoRules
{
    public const TITLE_MAX = 60;

    /** Storefront adı (public_name) — mağaza başlığı; kolon 255, ama okunur ad için 70. */
    public const PUBLIC_NAME_MAX = 70;

    public const DESCRIPTION_MAX = 160;

    public const KEYWORDS_MAX = 10;

    public const KEYWORD_LENGTH_MAX = 40;

    public const KEYWORDS_FIELD_MAX = 255;

    /**
     * @param  array<string, mixed>  $raw  AI cevabındaki seo nesnesi
     * @return array{meta_title: ?string, meta_description: ?string, meta_keywords: ?string}
     */
    public function apply(array $raw): array
    {
        return [
            'meta_title'       => $this->text($raw['meta_title'] ?? null, self::TITLE_MAX),
            'meta_description' => $this->text($raw['meta_description'] ?? null, self::DESCRIPTION_MAX),
            'meta_keywords'    => $this->keywords($raw['meta_keywords'] ?? null),
        ];
    }

    public function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = preg_replace('/[*_`#>]+/u', '', $value);
        $text = trim(preg_replace('/\s+/u', ' ', $text), " \t\n\r\0\x0B\"'“”‘’");

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:-–|');
    }

    /** @param  string|list<string>|null  $value */
    public function keywords(mixed $value): ?string
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        $keywords = [];
        foreach ($items as $item) {
            $word = $this->lower(trim(preg_replace('/\s+/u', ' ', (string) $item), " \t\"'#"));
            if ($word === '' || mb_strlen($word) > self::KEYWORD_LENGTH_MAX || in_array($word, $keywords, true)) {
                continue;
            }
            if (mb_strlen(implode(', ', [...$keywords, $word])) > self::KEYWORDS_FIELD_MAX) {
                break;
            }
            $keywords[] = $word;
            if (count($keywords) === self::KEYWORDS_MAX) {
                break;
            }
        }

        return $keywords === [] ? null : implode(', ', $keywords);
    }

    /** Türkçe küçük harf: I → ı, İ → i. */
    private function lower(string $text): string
    {
        return mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $text));
    }
}
