<?php

namespace Modules\Creative\Services\Enhancement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Python (OpenCV dnn_superres + Pillow) tabanlı görsel iyileştirici.
 *
 * {@see \Modules\Creative\Services\Rendering\PythonRenderer} ile aynı çağrı desenini
 * kullanır: payload stdin'e JSON olarak verilir, betik iyileştirilmiş PNG baytlarını
 * stdout'a yazar. Herhangi bir hata olursa (model yok, opencv yok, timeout, boş çıktı)
 * orijinal görselin yolu döndürülür — pipeline iyileştirme uğruna asla bozulmaz.
 */
class PythonImageEnhancer implements ImageEnhancerContract
{
    /** OpenCV model adı → model dosyası önek eşlemesi. */
    private const MODEL_FILE_PREFIX = [
        'fsrcnn' => 'FSRCNN',
        'edsr'   => 'EDSR',
        'lapsrn' => 'LapSRN',
        'espcn'  => 'ESPCN',
    ];

    public function enhance(string $sourcePath): string
    {
        if (! is_file($sourcePath)) {
            return $sourcePath;
        }

        try {
            $payload = $this->buildPayload($sourcePath);
            $json    = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $result = Process::timeout($this->timeout())
                ->input($json)
                ->run([$this->pythonBin(), $this->script()]);

            if ($result->failed()) {
                return $this->degrade($sourcePath, sprintf(
                    'exit %d: %s',
                    $result->exitCode() ?? -1,
                    trim($result->errorOutput()) ?: 'bilinmeyen hata',
                ));
            }

            $bytes = $result->output();
            if ($bytes === '') {
                return $this->degrade($sourcePath, 'boş çıktı');
            }

            return ImageFile::temp($bytes, 'png');
        } catch (\Throwable $e) {
            return $this->degrade($sourcePath, $e->getMessage());
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(string $sourcePath): array
    {
        $unsharp = (array) config('creative.enhance.unsharp', []);

        return [
            'input_path'        => $sourcePath,
            'model_path'        => $this->modelPath(),
            'model_name'        => (string) config('creative.enhance.model_name', 'fsrcnn'),
            'scale'             => (int) config('creative.enhance.scale', 2),
            'max_side'          => (int) config('creative.enhance.max_side', 2048),
            'unsharp_radius'    => (float) ($unsharp['radius'] ?? 2.0),
            'unsharp_percent'   => (int) ($unsharp['percent'] ?? 120),
            'unsharp_threshold' => (int) ($unsharp['threshold'] ?? 3),
            'contrast'          => (float) config('creative.enhance.contrast', 1.04),
            'saturation'        => (float) config('creative.enhance.saturation', 1.03),
            'mime'              => 'image/png',
        ];
    }

    /**
     * model_dir + model_name + scale'den dosya yolunu kurar (ör. .../FSRCNN_x2.pb).
     * Dosya yoksa boş string döner; Python tarafı Lanczos fallback'ine düşer.
     */
    private function modelPath(): string
    {
        $dir   = (string) config('creative.enhance.model_dir', '');
        $name  = strtolower((string) config('creative.enhance.model_name', 'fsrcnn'));
        $scale = (int) config('creative.enhance.scale', 2);

        $prefix = self::MODEL_FILE_PREFIX[$name] ?? null;
        if ($dir === '' || $prefix === null) {
            return '';
        }

        $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . sprintf('%s_x%d.pb', $prefix, $scale);

        return is_file($path) ? $path : '';
    }

    private function degrade(string $sourcePath, string $reason): string
    {
        Log::warning('Creative görsel iyileştirme atlandı (orijinal korunuyor).', [
            'source' => $sourcePath,
            'reason' => $reason,
        ]);

        return $sourcePath;
    }

    private function pythonBin(): string
    {
        return (string) config('creative.enhance.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.enhance.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.enhance.timeout', 120);
    }
}
