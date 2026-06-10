<?php

namespace Modules\Creative\Services\Ai\Support;

use Illuminate\Support\Str;

/**
 * Hashtag normalizasyonu (caption sürücüleri arasında paylaşılır).
 */
class HashtagHelper
{
    /**
     * Ham etiket listesini '#camelCase' biçiminde temiz, benzersiz diziye çevirir.
     *
     * @param  array<int,mixed>  $raw
     * @return array<int,string>
     */
    public static function normalize(array $raw, int $max = 12): array
    {
        $out = [];

        foreach ($raw as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            // '#', boşluk ve aksanları temizleyip kelimeleri birleştir.
            $clean = preg_replace('/[#\s]+/u', ' ', $tag);
            $clean = Str::of($clean)->ascii()->squish()->studly()->value();

            if ($clean === '') {
                continue;
            }

            $hashtag = '#' . lcfirst($clean);
            if (! in_array($hashtag, $out, true)) {
                $out[] = $hashtag;
            }

            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Serbest metinden hashtag'leri ayıklar (#... biçimindeki kelimeler).
     *
     * @return array<int,string>
     */
    public static function extractFromText(string $text): array
    {
        preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $m);

        return self::normalize($m[1] ?? []);
    }
}
