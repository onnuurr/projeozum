<?php

namespace Modules\TrainingIngest\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\TrainingIngest\Models\Asset;
use RuntimeException;

/**
 * training.assets'in tek yazma noktası — hash hesaplama, tekilleştirme, R2'ye yükleme
 * ve yükleme-sonrası doğrulama burada toplanır (bkz. Modules/TrainingIngest/README.md
 * "Depolama"). Public erişim kapalı (config/filesystems.php: 'training' disk,
 * visibility=private); okuma her zaman {@see temporaryUrl()} ile.
 */
class TrainingStorageService
{
    private const KEY_FORMAT = '%s/%s/%s/%s/%s_%02d_%s.%s';

    public function __construct(private readonly string $disk = 'training') {}

    /**
     * Bir yerel dosyayı training bucket'ına yükler ve karşılık gelen training.assets
     * satırını döndürür. Aynı içerik (sha256) daha önce yüklendiyse yeniden YÜKLEMEZ,
     * mevcut Asset'i döndürür — spec'in "aynı görsel iki kez yüklenmesin" kuralı.
     */
    public function store(string $localPath, string $sourceGenerationId, string $role, int $position, ?Carbon $at = null): Asset
    {
        $bytes = @file_get_contents($localPath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException("Eğitim verisi için görsel okunamadı: {$localPath}");
        }

        $sha256 = hash('sha256', $bytes);

        $existing = Asset::query()->where('content_sha256', $sha256)->first();
        if ($existing) {
            return $existing;
        }

        $at ??= Carbon::now();
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION)) ?: 'bin';
        $key = sprintf(
            self::KEY_FORMAT,
            $at->format('Y'), $at->format('m'), $at->format('d'),
            $sourceGenerationId, $role, $position, substr($sha256, 0, 12), $ext,
        );

        // 'throw' => true (config/filesystems.php) zaten sessiz başarısızlığı engeller;
        // burada AYRICA boyut doğrulaması yapılır (spec: "nesne gerçekten var mı, boyut
        // eşleşiyor mu").
        Storage::disk($this->disk)->put($key, $bytes);

        $remoteSize = Storage::disk($this->disk)->size($key);
        if ($remoteSize !== strlen($bytes)) {
            throw new RuntimeException(
                "Eğitim görseli yüklendi ama boyut doğrulaması başarısız: {$key} (beklenen ".strlen($bytes).", bulunan {$remoteSize})."
            );
        }

        [$width, $height] = $this->dimensions($bytes);

        return Asset::create([
            'storage_bucket' => (string) config("filesystems.disks.{$this->disk}.bucket"),
            'storage_key'    => $key,
            'content_sha256' => $sha256,
            'mime_type'      => $this->mimeFor($ext),
            'byte_size'      => strlen($bytes),
            'width'          => $width,
            'height'         => $height,
            'role'           => $role,
        ]);
    }

    /**
     * Süreli (varsayılan 15 dk) imzalı okuma URL'i — public erişim kapalı olduğu için
     * eğitim görsellerine erişimin TEK yolu budur (export CLI, admin önizleme vb.).
     */
    public function temporaryUrl(Asset $asset, int $minutes = 15): string
    {
        return Storage::disk($this->disk)->temporaryUrl($asset->storage_key, now()->addMinutes($minutes));
    }

    /**
     * Bir asset'in içeriğini geçici bir yerel dosyaya indirir (export CLI'ın hash
     * doğrulaması için); çağıran temizlemekten sorumludur.
     */
    public function download(Asset $asset, string $toLocalPath): void
    {
        $bytes = Storage::disk($this->disk)->get($asset->storage_key);
        if ($bytes === null) {
            throw new RuntimeException("Eğitim görseli R2'de bulunamadı: {$asset->storage_key}");
        }

        if (file_put_contents($toLocalPath, $bytes) === false) {
            throw new RuntimeException("Eğitim görseli yerel diske yazılamadı: {$toLocalPath}");
        }
    }

    /**
     * @return array{0:?int,1:?int}
     */
    private function dimensions(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);

        return $info !== false ? [$info[0], $info[1]] : [null, null];
    }

    private function mimeFor(string $ext): string
    {
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/png',
        };
    }
}
