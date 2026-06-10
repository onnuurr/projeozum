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
