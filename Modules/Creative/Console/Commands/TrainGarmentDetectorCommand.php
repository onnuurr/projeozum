<?php

namespace Modules\Creative\Console\Commands;

use App\Support\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Modules\Creative\Models\GarmentLabel;
use Modules\Creative\Models\GarmentScan;

/**
 * Manuel işaretlenmiş (bkz. Faz G.2 etiketleme aracı) giysi parça kutularından
 * torchvision tabanlı dedektörü fine-tune eder — {@see \Modules\Creative\Services\GarmentScanService}
 * DeepFashion2/Fashionpedia GİBİ hazır bir dataset yerine tamamen bu mağazanın
 * kendi ürün fotoğraflarından öğrenir (bkz. ROADMAP.md Faz G lisans kararı).
 *
 * Bilinçli olarak OTOMATİK ZAMANLANMAZ (routes/console.php'ye eklenmez) —
 * eğitim dakikalar/saatler sürebilir (CPU) ve model kalitesini admin'in
 * gözden geçirmesi gerekir; creative:review-report gibi rutin bir bakım
 * görevi değildir.
 */
class TrainGarmentDetectorCommand extends Command
{
    protected $signature = 'creative:train-garment-detector
        {--epochs= : Varsayılan config değerini geçersiz kılar}
        {--dry-run : Eğitimi ÇALIŞTIRMA, yalnızca etiket başına örnek sayısını raporla}';

    protected $description = 'Manuel işaretlenmiş giysi parça kutularından torchvision dedektörünü fine-tune eder (salt kendi verinizle — bkz. lisans kararı)';

    public function handle(): int
    {
        $minExamples = (int) config('creative.garment_detection.min_examples_per_label', 40);

        $countsByLabel = $this->countManualExamplesByLabel();

        $this->renderCounts($countsByLabel, $minExamples);

        $qualifying = array_filter($countsByLabel, fn ($count) => $count >= $minExamples);

        if ($qualifying === []) {
            $this->components->warn(sprintf(
                'Hiçbir etiket asgari örnek sayısına (%d) ulaşmadı. Önce manuel etiketleme aracıyla daha fazla parça işaretleyin.',
                $minExamples,
            ));

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('%d etiket eğitime dahil edilmeye hazır (dry-run, eğitim çalıştırılmadı).', count($qualifying)));

            return self::SUCCESS;
        }

        return $this->runTraining(array_keys($qualifying));
    }

    /**
     * @return array<string,int> label_key => manuel işaretlenmiş örnek sayısı
     */
    private function countManualExamplesByLabel(): array
    {
        $counts = [];

        GarmentScan::query()
            ->whereNotNull('detections')
            ->get(['id', 'detections'])
            ->each(function (GarmentScan $scan) use (&$counts) {
                foreach ((array) $scan->detections as $d) {
                    if (($d['source'] ?? null) !== 'manual') {
                        continue;
                    }
                    $key = $d['label_key'] ?? null;
                    if (! $key) {
                        continue;
                    }
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            });

        return $counts;
    }

    private function renderCounts(array $counts, int $minExamples): void
    {
        if ($counts === []) {
            $this->components->warn('Hiç manuel işaretlenmiş parça bulunamadı.');

            return;
        }

        arsort($counts);
        $this->table(
            ['Etiket', 'Manuel örnek', 'Eşik', 'Durum'],
            collect($counts)->map(fn ($count, $key) => [
                $key, $count, $minExamples, $count >= $minExamples ? 'Hazır' : 'Yetersiz',
            ])->values()->all(),
        );
    }

    /**
     * @param  array<int,string>  $labelKeys  Eğitime dahil edilecek etiket anahtarları
     */
    private function runTraining(array $labelKeys): int
    {
        $workspace = storage_path('app/creative-garment-training/' . now()->format('Y_m_d_His'));
        File::ensureDirectoryExists($workspace . '/images');

        [$categories, $keyToCategoryId] = $this->buildCategories($labelKeys);
        [$images, $annotations] = $this->exportCocoData($workspace, $labelKeys, $keyToCategoryId);

        if ($images === []) {
            $this->components->error('Hiçbir eğitim görseli diskten okunamadı — eğitim iptal edildi.');
            File::deleteDirectory($workspace);

            return self::FAILURE;
        }

        $annotationsPath = $workspace . '/coco_annotations.json';
        File::put($annotationsPath, json_encode([
            'images'      => $images,
            'annotations' => $annotations,
            'categories'  => $categories,
        ], JSON_UNESCAPED_UNICODE));

        $modelVersion = 'garment_parts_' . now()->format('Y_m_d_His');
        $payload = [
            'images_dir'       => $workspace . '/images',
            'annotations_path' => $annotationsPath,
            'output_path'      => (string) config('creative.garment_detection.weights_path'),
            'model_version'    => $modelVersion,
            'epochs'           => (int) ($this->option('epochs') ?: 10),
        ];

        $this->components->info(sprintf('Eğitim başlıyor: %d görsel, %d etiket, model_version=%s', count($images), count($categories), $modelVersion));

        $result = Process::timeout((int) config('creative.garment_detection.train_timeout', 3600))
            ->input(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->run([
                (string) config('creative.garment_detection.python_bin', 'python3'),
                (string) config('creative.garment_detection.train_script'),
            ]);

        File::deleteDirectory($workspace);

        if ($result->failed()) {
            $this->components->error('Eğitim başarısız: ' . (trim($result->errorOutput()) ?: 'bilinmeyen hata'));

            return self::FAILURE;
        }

        $this->components->info('Eğitim tamamlandı: ' . trim($result->output()));

        return self::SUCCESS;
    }

    /**
     * @param  array<int,string>  $labelKeys
     * @return array{0:array<int,array{id:int,name:string,key:string}>,1:array<string,int>}
     */
    private function buildCategories(array $labelKeys): array
    {
        $labelsByKey = GarmentLabel::query()->whereIn('key', $labelKeys)->get()->keyBy('key');

        $categories = [];
        $keyToCategoryId = [];
        $nextId = 1;

        foreach ($labelKeys as $key) {
            $label = $labelsByKey->get($key);
            $categories[] = ['id' => $nextId, 'name' => $label?->display ?? $key, 'key' => $key];
            $keyToCategoryId[$key] = $nextId;
            $nextId++;
        }

        return [$categories, $keyToCategoryId];
    }

    /**
     * @param  array<int,string>  $labelKeys
     * @param  array<string,int>  $keyToCategoryId
     * @return array{0:array<int,array>,1:array<int,array>}
     */
    private function exportCocoData(string $workspace, array $labelKeys, array $keyToCategoryId): array
    {
        $images = [];
        $annotations = [];
        $annotationId = 1;

        $scans = GarmentScan::query()
            ->whereNotNull('detections')
            ->whereNotNull('source_path')
            ->get(['id', 'source_path', 'detections']);

        foreach ($scans as $scan) {
            $manualBoxes = collect((array) $scan->detections)
                ->filter(fn ($d) => ($d['source'] ?? null) === 'manual' && in_array($d['label_key'] ?? null, $labelKeys, true))
                ->values();

            if ($manualBoxes->isEmpty()) {
                continue;
            }

            $localPath = Media::localPath($scan->source_path);
            if (! $localPath || ! is_file($localPath)) {
                continue;
            }

            $size = @getimagesize($localPath);
            if (! $size) {
                continue;
            }
            [$width, $height] = $size;

            $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION)) ?: 'jpg';
            $fileName = $scan->id . '.' . $ext;
            File::copy($localPath, $workspace . '/images/' . $fileName);

            $images[] = ['id' => $scan->id, 'file_name' => $fileName, 'width' => $width, 'height' => $height];

            foreach ($manualBoxes as $box) {
                $bbox = $box['bbox'] ?? null;
                if (! is_array($bbox)) {
                    continue;
                }

                $annotations[] = [
                    'id'           => $annotationId++,
                    'image_id'     => $scan->id,
                    'category_id'  => $keyToCategoryId[$box['label_key']],
                    'bbox'         => [
                        (float) $bbox['x'] * $width,
                        (float) $bbox['y'] * $height,
                        (float) $bbox['w'] * $width,
                        (float) $bbox['h'] * $height,
                    ],
                    'area'         => (float) $bbox['w'] * $width * (float) $bbox['h'] * $height,
                    'iscrowd'      => 0,
                ];
            }
        }

        return [$images, $annotations];
    }
}
