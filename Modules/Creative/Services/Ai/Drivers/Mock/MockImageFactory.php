<?php

namespace Modules\Creative\Services\Ai\Drivers\Mock;

use RuntimeException;

/**
 * Anahtarsız dev/test için GD ile basit placeholder PNG üretir.
 */
class MockImageFactory
{
    /**
     * Düz renkli, etiketli bir PNG'nin ham baytlarını döndürür.
     */
    public function make(int $width, int $height, string $hexColor, string $label): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Mock AI görseli için PHP GD eklentisi gerekli.');
        }

        $im = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = $this->rgb($hexColor);
        imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));

        // Kontrastlı etiket rengi (parlaklığa göre siyah/beyaz).
        $luma = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
        $text = $luma > 140
            ? imagecolorallocate($im, 17, 24, 39)
            : imagecolorallocate($im, 255, 255, 255);

        imagestring($im, 5, 16, 16, substr($label, 0, 120), $text);

        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [204, 204, 204];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
