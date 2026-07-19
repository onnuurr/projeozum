<?php

namespace Modules\Creative\Services\Enhancement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Python (open_clip, yerel zero-shot CLIP) tabanlı giysi detay sınıflandırıcı.
 *
 * {@see \Modules\Creative\Services\Enhancement\PythonGarmentPreparer} ile aynı
 * çağrı desenini kullanır ancak çıktı JSON'dur (bkz. inspect_template.py):
 * payload stdin'e JSON olarak verilir, betik {"results": [...]} JSON'unu
 * stdout'a yazar. Herhangi bir hata olursa (model yok, timeout, bozuk çıktı)
 * her görsel için boş öneri listesi döndürülür — giydirme pipeline'ı asla bu
 * adım yüzünden bozulmaz, kullanıcının elle girdiği/gireceği etikete düşülür.
 */
class PythonGarmentDetailClassifier implements GarmentDetailClassifierContract
{
    public function classify(array $imagePaths): array
    {
        $imagePaths = array_values(array_unique(array_filter($imagePaths, 'is_file')));

        if ($imagePaths === []) {
            return [];
        }

        try {
            $payload = [
                'image_paths'           => $imagePaths,
                'top_k'                 => (int) config('creative.detail_classification.top_k', 3),
                'multi_label_threshold' => (float) config('creative.detail_classification.threshold', 0.15),
                'model_name'            => (string) config('creative.detail_classification.model_name', 'ViT-B-32'),
                'pretrained'            => (string) config('creative.detail_classification.pretrained', 'laion2b_s34b_b79k'),
            ];
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $result = Process::timeout($this->timeout())
                ->input($json)
                ->run([$this->pythonBin(), $this->script()]);

            if ($result->failed()) {
                return $this->degrade($imagePaths, sprintf(
                    'exit %d: %s',
                    $result->exitCode() ?? -1,
                    trim($result->errorOutput()) ?: 'bilinmeyen hata',
                ));
            }

            $decoded = json_decode($result->output(), true);
            if (! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
                return $this->degrade($imagePaths, 'geçersiz çıktı');
            }

            $byPath = array_fill_keys($imagePaths, []);
            foreach ($decoded['results'] as $row) {
                if (isset($row['path'])) {
                    $byPath[$row['path']] = $row['labels'] ?? [];
                }
            }

            return $byPath;
        } catch (\Throwable $e) {
            return $this->degrade($imagePaths, $e->getMessage());
        }
    }

    private function degrade(array $imagePaths, string $reason): array
    {
        Log::warning('Creative giysi detay sınıflandırması atlandı.', ['reason' => $reason]);

        return array_fill_keys($imagePaths, []);
    }

    private function pythonBin(): string
    {
        return (string) config('creative.detail_classification.python_bin', 'python3');
    }

    private function script(): string
    {
        return (string) config('creative.detail_classification.script');
    }

    private function timeout(): int
    {
        return (int) config('creative.detail_classification.timeout', 30);
    }
}
