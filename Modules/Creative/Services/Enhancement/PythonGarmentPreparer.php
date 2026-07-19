<?php

namespace Modules\Creative\Services\Enhancement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Python (Pillow) tabanlı giydirme-öncesi görsel hazırlayıcı.
 *
 * {@see \Modules\Creative\Services\Enhancement\PythonImageEnhancer} ile aynı çağrı
 * desenini kullanır: payload stdin'e JSON olarak verilir, betik hazırlanmış PNG
 * baytlarını stdout'a yazar. Herhangi bir hata olursa (Pillow yok, timeout, boş
 * çıktı) orijinal görselin yolu döndürülür — giydirme pipeline'ı asla bu adım
 * yüzünden bozulmaz.
 */
class PythonGarmentPreparer implements GarmentPrepContract
{
    public function prepare(string $sourcePath): string
    {
        if (! is_file($sourcePath)) {
            return $sourcePath;
        }

        try {
            $payload = [
                'input_path'  => $sourcePath,
                'max_side'    => (int) config('creative.garment_prep.max_side', 2048),
                'trim_border' => (bool) config('creative.garment_prep.trim_border', true),
                'mime'        => 'image/png',
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

    private function degrade(string $sourcePath, string $reason): string
    {
        Log::warning('Creative giysi görseli hazırlığı atlandı (orijinal korunuyor).', [
            'source' => $sourcePath,
            'reason' => $reason,
        ]);

        return $sourcePath;
    }

    private function pythonBin(): string
    {
        return (string) config('creative.garment_prep.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.garment_prep.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.garment_prep.timeout', 60);
    }
}
