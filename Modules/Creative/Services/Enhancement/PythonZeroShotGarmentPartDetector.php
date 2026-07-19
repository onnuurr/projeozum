<?php

namespace Modules\Creative\Services\Enhancement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * OWLv2 tabanlı zero-shot giysi parça dedektörü (Faz G.3b — bkz. ROADMAP.md).
 *
 * {@see PythonGarmentPartDetector} ile aynı çağrı desenini kullanır (stdin JSON
 * -> stdout JSON, hata durumunda boş liste), ama fine-tune ağırlığı GEREKTİRMEZ
 * — henüz hiç eğitilmiş model yokken bootstrap öneri kutuları üretir. Her
 * tespite 'source' => 'zeroshot' damgası eklenir: bu, GarmentScanService'in
 * normalizeAndNameLabels()'ı bu değeri koruyacak şekilde okur ve sonuç olarak
 * bu tespitler cropsForTryOn() (source='auto' filtreler) ile
 * creative:train-garment-detector (source='manual' filtreler) tarafından bir
 * insan etiketleme aracında onaylayana kadar GÖRÜLMEZ.
 */
class PythonZeroShotGarmentPartDetector implements GarmentPartDetectorContract
{
    private ?string $resolvedModelVersion = null;

    public function detect(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            return [];
        }

        try {
            $payload = [
                'image_path'     => $imagePath,
                'model_name'     => (string) config('creative.garment_detection.zero_shot.model_name'),
                'min_confidence' => (float) config('creative.garment_detection.zero_shot.min_confidence', 0.15),
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

            $this->resolvedModelVersion = (string) ($decoded['model_version'] ?? 'unknown_zeroshot');

            return array_map(
                fn (array $d) => [...$d, 'source' => 'zeroshot'],
                $decoded['detections'],
            );
        } catch (\Throwable $e) {
            return $this->degrade($e->getMessage());
        }
    }

    public function modelVersion(): string
    {
        return $this->resolvedModelVersion ?? 'unknown_zeroshot';
    }

    private function degrade(string $reason): array
    {
        Log::warning('Creative zero-shot giysi parça tespiti atlandı.', ['reason' => $reason]);

        return [];
    }

    private function pythonBin(): string
    {
        return (string) config('creative.garment_detection.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.garment_detection.zero_shot.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.garment_detection.zero_shot.timeout', 60);
    }
}
