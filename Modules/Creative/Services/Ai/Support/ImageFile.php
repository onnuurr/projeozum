<?php

namespace Modules\Creative\Services\Ai\Support;

use RuntimeException;

/**
 * AI sürücüleri ve orchestrator için görsel I/O yardımcıları.
 * Ara çıktılar geçici dosyalara yazılır; pipeline sonunda temizlenir.
 */
class ImageFile
{
    /**
     * Ham baytları benzersiz bir geçici dosyaya yazar, mutlak yolu döndürür.
     */
    public static function temp(string $bytes, string $ext = 'png'): string
    {
        if ($bytes === '') {
            throw new RuntimeException('Boş görsel baytı geçici dosyaya yazılamaz.');
        }

        $path = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
            . 'ai_' . bin2hex(random_bytes(8)) . '.' . $ext;

        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * Dosya içeriğini base64 olarak döndürür.
     */
    public static function base64(string $path): string
    {
        $bin = @file_get_contents($path);
        if ($bin === false) {
            throw new RuntimeException("Görsel okunamadı: {$path}");
        }

        return base64_encode($bin);
    }

    /**
     * Dosyayı 'data:<mime>;base64,...' URI'sine çevirir (URL gerektirmeyen API'ler için).
     */
    public static function dataUri(string $path): string
    {
        return 'data:' . self::mime($path) . ';base64,' . self::base64($path);
    }

    public static function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };
    }

    /**
     * Ham görsel baytlarını hedef formata (webp/jpeg) yeniden kodlar — nihai
     * diske yazma adımında PNG yerine kullanılmak üzere (disk boyutu için).
     * GD decode/encode başarısız olursa (format desteği yok, bozuk bayt vb.)
     * baytlar DEĞİŞMEDEN 'png' uzantısıyla döner — enhancer'daki "asla bozma"
     * ilkesiyle aynı graceful degrade.
     *
     * @return array{bytes:string,ext:string}
     */
    public static function encode(string $bytes, string $format = 'webp', int $quality = 90): array
    {
        $fallback = ['bytes' => $bytes, 'ext' => 'png'];

        if (! function_exists('imagecreatefromstring')) {
            return $fallback;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return $fallback;
        }

        try {
            imagesavealpha($image, true);

            $encoded = match ($format) {
                'webp'        => self::gdEncode($image, fn ($im, $tmp) => imagewebp($im, $tmp, $quality)),
                'jpg', 'jpeg' => self::gdEncode($image, fn ($im, $tmp) => imagejpeg($im, $tmp, $quality)),
                default       => null,
            };

            if ($encoded === null) {
                return $fallback;
            }

            return ['bytes' => $encoded, 'ext' => $format === 'jpeg' ? 'jpg' : $format];
        } catch (\Throwable) {
            // GD build'inde ilgili format desteği yoksa (örn. imagewebp() tanımsız)
            // çağrı bir \Error fırlatır — docblock'un vaat ettiği "PNG'ye graceful
            // degrade" burada olur, aksi halde render job'u başarısız olurdu.
            return $fallback;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * @param  \GdImage  $image
     * @param  callable(\GdImage,string):bool  $writer
     */
    private static function gdEncode($image, callable $writer): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'imgenc_');
        if ($tmp === false) {
            return null;
        }

        try {
            $ok  = $writer($image, $tmp);
            $out = $ok ? @file_get_contents($tmp) : false;

            return ($out !== false && $out !== '') ? $out : null;
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Geçici dosyaları güvenle siler.
     *
     * @param  array<int,string|null>  $paths
     */
    public static function delete(array $paths): void
    {
        foreach ($paths as $p) {
            if (is_string($p) && $p !== '' && is_file($p)) {
                @unlink($p);
            }
        }
    }
}
