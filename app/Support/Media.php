<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Görsel/dosya adreslerini tek noktadan çözen yardımcı.
 *
 * DB'de yalnızca relative path (örn. "products/1/x.png") saklanır; tam URL
 * okuma anında aktif medya diski üzerinden üretilir. Disk 'public' iken
 * yerel /storage URL'i, 's3' (Cloudflare R2/CDN) iken AWS_URL tabanlı CDN
 * URL'i döner. Böylece host hiçbir zaman DB'ye gömülmez.
 */
final class Media
{
    /** Aktif medya diski (config/media.php → MEDIA_DISK). */
    public static function disk(): string
    {
        return config('media.disk', 'public');
    }

    /**
     * Relative path'i tam URL'e çevirir.
     *
     * Zaten mutlak olan değerler (eski full-URL kalıntıları, harici linkler,
     * data URI'ları) olduğu gibi döndürülür — idempotent.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, 'data:')) {
            return $path;
        }

        return Storage::disk(self::disk())->url(ltrim($path, '/'));
    }

    /**
     * Relative bir medya yolunu, render motorunun (Python) okuyabileceği yerel
     * mutlak dosya yoluna çevirir. Disk 'public'/'local' ise gerçek dosya yolu
     * döner (indirme yok); uzak disk (R2/S3) ise dosya kalıcı yerel cache'e
     * indirilip o yol döner. Zaten var olan mutlak yerel yol korunur.
     *
     * @param  string|null  $disk  Belirli bir disk (örn. creative); yoksa MEDIA_DISK.
     */
    public static function localPath(?string $path, ?string $disk = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        // Zaten mutlak yerel yol (örn. paketle gelen font)
        if (is_file($path)) {
            return $path;
        }

        $disk ??= self::disk();
        $rel     = ltrim($path, '/');
        $storage = Storage::disk($disk);

        if (! $storage->exists($rel)) {
            return null;
        }

        // Yerel disk → gerçek dosya yolu
        if (in_array($disk, ['public', 'local'], true)) {
            $full = $storage->path($rel);

            return is_file($full) ? $full : null;
        }

        // Uzak disk (R2/S3) → kalıcı yerel cache'e indir, tekrar kullan.
        // store() dosya adları içerik-rastgele olduğundan path-bazlı cache güvenlidir.
        $cacheDir = storage_path('app/private/media-cache');
        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $local = $cacheDir . DIRECTORY_SEPARATOR
            . sha1($disk . '|' . $rel) . '.' . (pathinfo($rel, PATHINFO_EXTENSION) ?: 'bin');

        if (! is_file($local)) {
            $bin = $storage->get($rel);
            if ($bin === null || $bin === '') {
                return null;
            }
            file_put_contents($local, $bin);
        }

        return $local;
    }
}
