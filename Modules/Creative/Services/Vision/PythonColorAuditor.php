<?php

namespace Modules\Creative\Services\Vision;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Python (numpy + Pillow) tabanlı renk sadakati ölçümü.
 *
 * {@see \Modules\Creative\Services\Vision\PythonTesseractTextRecognizer} ile aynı
 * çağrı deseni: payload stdin'e JSON, betik ölçüm sonucunu stdout'a JSON olarak
 * yazar. Hata/timeout/düşük-güvenli maske durumunda `null` döner — çağıran taraf
 * ({@see \Modules\Creative\Services\ProductOnModelService}) bunu "ölçülemedi"
 * sayıp sessizce atlar, üretim asla bu adım yüzünden düşmez.
 */
class PythonColorAuditor implements ColorAuditorContract
{
    public function measure(string $originalGarmentPath, string $posedPath, string $outputPath): ?array
    {
        if (! is_file($originalGarmentPath) || ! is_file($posedPath) || ! is_file($outputPath)) {
            return null;
        }

        try {
            $payload = [
                'original_path'  => $originalGarmentPath,
                'posed_path'     => $posedPath,
                'output_path'    => $outputPath,
                'diff_threshold' => (float) config('creative.color_fidelity.diff_threshold', 28.0),
                'bg_tolerance'   => (float) config('creative.color_fidelity.bg_tolerance', 24.0),
                'mask_min_ratio' => (float) config('creative.color_fidelity.mask_min_ratio', 0.02),
                'mask_max_ratio' => (float) config('creative.color_fidelity.mask_max_ratio', 0.6),
            ];

            $result = Process::timeout($this->timeout())
                ->input(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->run([$this->pythonBin(), $this->script()]);

            if ($result->failed()) {
                Log::warning('Creative renk denetimi başarısız.', [
                    'output' => $outputPath,
                    'error'  => trim($result->errorOutput()) ?: sprintf('exit %d', $result->exitCode() ?? -1),
                ]);

                return null;
            }

            $decoded = json_decode($result->output(), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('Creative renk denetimi istisna fırlattı.', [
                'output' => $outputPath,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function pythonBin(): string
    {
        return (string) config('creative.color_fidelity.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.color_fidelity.audit.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.color_fidelity.audit.timeout', 30);
    }
}
