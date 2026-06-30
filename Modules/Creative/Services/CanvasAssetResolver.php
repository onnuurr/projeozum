<?php

namespace Modules\Creative\Services;

use App\Support\Media;

/**
 * product_images.path gibi relative değerleri Python'un okuyabileceği yerel
 * dosya yollarına çözer. http/data kaynakları ve uzak disk (R2/S3) dosyaları
 * geçici dosyaya yazılır, render sonrası cleanup() ile temizlenir.
 *
 * Aktif medya diski 'public' iken dosya doğrudan yerel diskten okunur
 * (indirme yok); 's3' (Cloudflare R2/CDN) iken byte'lar temp'e indirilir.
 */
class CanvasAssetResolver
{
    /** @var array<int,string> Render sonrası silinecek geçici dosyalar */
    private array $tempFiles = [];

    /**
     * Verilen kaynağı yerel mutlak dosya yoluna çözer; çözülemezse null.
     */
    public function toLocalPath(string $src): ?string
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        // data:image/png;base64,...
        if (str_starts_with($src, 'data:')) {
            return $this->writeDataUri($src);
        }

        // http(s) → indir
        if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
            return $this->downloadToTemp($src);
        }

        // /storage/... → medya diskindeki relative path'e indirge (geriye dönük uyum)
        $publicBase = '/storage/';
        if (str_starts_with($src, $publicBase)) {
            return $this->resolveFromDisk(substr($src, strlen($publicBase)));
        }

        // Mutlak dosya yolu
        if (is_file($src)) {
            return $src;
        }

        // Göreli medya yolu (örn. "products/1/foo.jpg")
        return $this->resolveFromDisk($src);
    }

    /**
     * Medya diskindeki relative path'i yerel mutlak yola çözer. Yerel disk
     * (public/local) ise gerçek dosya yolu döner; uzak disk (R2/S3) ise dosya
     * kalıcı yerel cache'e indirilip o yol döner (App\Support\Media::localPath).
     */
    private function resolveFromDisk(string $diskPath): ?string
    {
        return Media::localPath($diskPath);
    }

    public function cleanup(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
    }

    private function writeDataUri(string $src): ?string
    {
        $comma = strpos($src, ',');
        if ($comma === false) {
            return null;
        }

        $meta = substr($src, 5, $comma - 5); // "image/png;base64"
        $data = substr($src, $comma + 1);
        $bin  = str_contains($meta, 'base64') ? base64_decode($data, true) : urldecode($data);

        if ($bin === false || $bin === '') {
            return null;
        }

        $ext  = $this->extensionFromMime($meta);
        $path = $this->tempPath($ext);
        file_put_contents($path, $bin);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function downloadToTemp(string $url): ?string
    {
        $bin = @file_get_contents($url);
        if ($bin === false || $bin === '') {
            return null;
        }

        $ext  = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'img';
        $path = $this->tempPath($ext);
        file_put_contents($path, $bin);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function tempPath(string $ext): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
            . 'creative_' . bin2hex(random_bytes(8)) . '.' . $ext;
    }

    private function extensionFromMime(string $meta): string
    {
        return match (true) {
            str_contains($meta, 'png')  => 'png',
            str_contains($meta, 'jpeg') => 'jpg',
            str_contains($meta, 'jpg')  => 'jpg',
            str_contains($meta, 'webp') => 'webp',
            default                     => 'img',
        };
    }
}
