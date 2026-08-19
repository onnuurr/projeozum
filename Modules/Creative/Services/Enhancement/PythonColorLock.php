<?php

namespace Modules\Creative\Services\Enhancement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Services\Ai\Support\ImageFile;

/**
 * Python (numpy + Pillow, LAB renk transferi) tabanlı renk kilidi.
 *
 * {@see \Modules\Creative\Services\Enhancement\PythonGarmentPreparer} ile aynı
 * çağrı desenini kullanır: payload stdin'e JSON olarak verilir, betik düzeltilmiş
 * PNG baytlarını stdout'a yazar. Herhangi bir hata olursa (numpy/Pillow yok,
 * timeout, boş çıktı, düşük-güvenli maske) orijinal çıktının yolu döndürülür —
 * giydirme pipeline'ı asla bu adım yüzünden bozulmaz.
 */
class PythonColorLock implements ColorLockContract
{
    public function apply(string $outputPath, string $originalGarmentPath, string $posedPath): string
    {
        if (! is_file($outputPath) || ! is_file($originalGarmentPath) || ! is_file($posedPath)) {
            return $outputPath;
        }

        try {
            $payload = [
                'output_path'     => $outputPath,
                'original_path'   => $originalGarmentPath,
                'posed_path'      => $posedPath,
                'strength'        => (float) config('creative.color_fidelity.lock.strength', 0.85),
                'diff_threshold'  => (float) config('creative.color_fidelity.diff_threshold', 28.0),
                'bg_tolerance'    => (float) config('creative.color_fidelity.bg_tolerance', 24.0),
                'mask_min_ratio'  => (float) config('creative.color_fidelity.mask_min_ratio', 0.02),
                'mask_max_ratio'  => (float) config('creative.color_fidelity.mask_max_ratio', 0.6),
                'feather_radius'  => (int) config('creative.color_fidelity.lock.feather_radius', 6),
                'mime'            => 'image/png',
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $result = Process::timeout($this->timeout())
                ->input($json)
                ->run([$this->pythonBin(), $this->script()]);

            if ($result->failed()) {
                return $this->degrade($outputPath, sprintf(
                    'exit %d: %s',
                    $result->exitCode() ?? -1,
                    trim($result->errorOutput()) ?: 'bilinmeyen hata',
                ));
            }

            $bytes = $result->output();
            if ($bytes === '') {
                return $this->degrade($outputPath, 'boş çıktı');
            }

            return ImageFile::temp($bytes, 'png');
        } catch (\Throwable $e) {
            return $this->degrade($outputPath, $e->getMessage());
        }
    }

    private function degrade(string $outputPath, string $reason): string
    {
        Log::warning('Creative renk kilidi atlandı (orijinal çıktı korunuyor).', [
            'output' => $outputPath,
            'reason' => $reason,
        ]);

        return $outputPath;
    }

    private function pythonBin(): string
    {
        return (string) config('creative.color_fidelity.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.color_fidelity.lock.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.color_fidelity.lock.timeout', 30);
    }
}
