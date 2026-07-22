<?php

namespace Modules\Creative\Services\Vision;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Python (pytesseract) tabanlı OCR — {@see \Modules\Creative\Services\Enhancement\PythonImageEnhancer}
 * ile aynı çağrı deseni: payload stdin'e JSON, betik kelime/kutu listesini
 * stdout'a JSON olarak yazar. Hata/timeout durumunda boş liste döner —
 * LayoutConstraintEngine bunu "hiç metin bulunamadı" olarak yorumlayıp
 * kompozisyonu reddeder (sessizce geçirmez).
 */
class PythonTesseractTextRecognizer implements TextRecognizerContract
{
    public function recognize(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            return [];
        }

        try {
            $payload = [
                'input_path'     => $imagePath,
                'lang'           => (string) config('creative.composition.ocr.lang', 'eng'),
                'min_confidence' => (float) config('creative.composition.ocr.min_confidence', 0.4),
            ];

            $result = Process::timeout($this->timeout())
                ->input(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                ->run([$this->pythonBin(), $this->script()]);

            if ($result->failed()) {
                Log::warning('Creative OCR başarısız.', [
                    'source' => $imagePath,
                    'error'  => trim($result->errorOutput()) ?: sprintf('exit %d', $result->exitCode() ?? -1),
                ]);

                return [];
            }

            $decoded = json_decode($result->output(), true);

            return is_array($decoded['words'] ?? null) ? $decoded['words'] : [];
        } catch (\Throwable $e) {
            Log::warning('Creative OCR istisna fırlattı.', [
                'source' => $imagePath,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function pythonBin(): string
    {
        return (string) config('creative.composition.ocr.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.composition.ocr.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.composition.ocr.timeout', 30);
    }
}
