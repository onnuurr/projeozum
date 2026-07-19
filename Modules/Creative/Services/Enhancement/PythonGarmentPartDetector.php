<?php

namespace Modules\Creative\Services\Enhancement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Python (torchvision) tabanlı giysi parça dedektörü.
 *
 * {@see PythonGarmentDetailClassifier} ile aynı çağrı desenini kullanır:
 * payload stdin'e JSON olarak verilir, betik ({@see detect_garment_parts.py})
 * bir JSON sonucu stdout'a yazar. Herhangi bir hata olursa (ağırlık dosyası
 * yok/bozuk, timeout, geçersiz çıktı) boş tespit listesi döner — giydirme
 * pipeline'ı asla bu adım yüzünden bozulmaz.
 */
class PythonGarmentPartDetector implements GarmentPartDetectorContract
{
    private ?string $resolvedModelVersion = null;

    public function detect(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            return [];
        }

        $weightsPath = (string) config('creative.garment_detection.weights_path');
        if ($weightsPath === '' || ! is_file($weightsPath)) {
            return [];
        }

        try {
            $payload = [
                'image_path'     => $imagePath,
                'weights_path'   => $weightsPath,
                'min_confidence' => (float) config('creative.garment_detection.min_confidence', 0.35),
                'max_detections' => (int) config('creative.garment_detection.max_detections', 20),
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $result = Process::timeout($this->timeout())
                ->input($json)
                ->run([$this->pythonBin(), $this->script()]);

            if ($result->failed()) {
                return $this->degrade(sprintf(
                    'exit %d: %s',
                    $result->exitCode() ?? -1,
                    trim($result->errorOutput()) ?: 'bilinmeyen hata',
                ));
            }

            $decoded = json_decode($result->output(), true);
            if (! is_array($decoded) || ! isset($decoded['detections']) || ! is_array($decoded['detections'])) {
                return $this->degrade('geçersiz çıktı');
            }

            $this->resolvedModelVersion = (string) ($decoded['model_version'] ?? 'unknown');

            return array_map(
                fn (array $d) => [...$d, 'source' => 'auto'],
                $decoded['detections'],
            );
        } catch (\Throwable $e) {
            return $this->degrade($e->getMessage());
        }
    }

    public function modelVersion(): string
    {
        return $this->resolvedModelVersion ?? 'unknown';
    }

    private function degrade(string $reason): array
    {
        Log::warning('Creative giysi parça tespiti atlandı.', ['reason' => $reason]);

        return [];
    }

    private function pythonBin(): string
    {
        return (string) config('creative.garment_detection.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.garment_detection.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.garment_detection.timeout', 30);
    }
}
