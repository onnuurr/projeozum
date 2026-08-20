<?php

namespace Modules\TrainingIngest\Console\Commands;

use Illuminate\Console\Command;
use Modules\TrainingIngest\Models\Asset;
use Modules\TrainingIngest\Models\Review;
use Modules\TrainingIngest\Services\TrainingStorageService;

/**
 * Fine-tune günü için dataset çıkarımı — training şemasından okur, R2'den görselleri
 * indirir, `manifest.jsonl` üretir. Yalnızca GÜNCEL kararları export eder (bkz.
 * training.reviews.superseded_by) — düzeltilmiş/eski kararlar dahil edilmez.
 */
class TrainingExportCommand extends Command
{
    protected $signature = 'training:export
        {--out= : Çıktı klasörü}
        {--verdict=approved : approved|rejected}
        {--from= : reviewed_at başlangıç (YYYY-MM-DD)}
        {--to= : reviewed_at bitiş (YYYY-MM-DD)}
        {--category= : Ürün kategorisine göre filtrele}';

    protected $description = 'Onaylanmış/reddedilmiş üretimleri manifest.jsonl + görsel klasörü olarak dışa aktarır.';

    public function handle(TrainingStorageService $storage): int
    {
        $out = $this->option('out');
        if (! $out) {
            $this->error('--out zorunludur.');

            return self::FAILURE;
        }

        $verdict = $this->option('verdict');
        if (! in_array($verdict, [Review::VERDICT_APPROVED, Review::VERDICT_REJECTED], true)) {
            $this->error("--verdict approved|rejected olmalı, '{$verdict}' verildi.");

            return self::FAILURE;
        }

        $assetsDir = rtrim($out, '/') . '/assets';
        if (! is_dir($assetsDir) && ! mkdir($assetsDir, 0755, true) && ! is_dir($assetsDir)) {
            $this->error("Çıktı klasörü oluşturulamadı: {$assetsDir}");

            return self::FAILURE;
        }

        $manifestPath = rtrim($out, '/') . '/manifest.jsonl';
        $manifest = fopen($manifestPath, 'w');
        if ($manifest === false) {
            $this->error("manifest.jsonl açılamadı: {$manifestPath}");

            return self::FAILURE;
        }

        $from = $this->option('from');
        $to = $this->option('to');
        $category = $this->option('category');

        $query = Review::query()
            ->where('verdict', $verdict)
            ->whereNull('superseded_by')
            ->with(['generationOutput.asset', 'generationOutput.generation.inputs.asset', 'generationOutput.generation.product', 'generationOutput.generation.promptVersion']);

        if ($from) {
            $query->where('reviewed_at', '>=', "{$from} 00:00:00");
        }
        if ($to) {
            $query->where('reviewed_at', '<=', "{$to} 23:59:59");
        }
        if ($category) {
            $query->whereHas('generationOutput.generation.product', fn ($q) => $q->where('category', $category));
        }

        $downloaded = [];
        $counts = ['exported' => 0, 'corrupt' => 0];

        foreach ($query->cursor() as $review) {
            $generation = $review->generationOutput->generation;
            $outputAsset = $review->generationOutput->asset;

            $outputLocal = $this->ensureLocal($outputAsset, $assetsDir, $storage, $downloaded);
            if (! $outputLocal) {
                $counts['corrupt']++;
                $this->warn("Bozuk/erişilemez çıktı görseli atlandı: generation_output {$review->generation_output_id}");

                continue;
            }

            $inputs = [];
            $corrupted = false;
            foreach ($generation->inputs as $input) {
                $local = $this->ensureLocal($input->asset, $assetsDir, $storage, $downloaded);
                if (! $local) {
                    $corrupted = true;

                    break;
                }
                $inputs[] = ['local_path' => $local, 'input_kind' => $input->input_kind, 'position' => $input->position];
            }

            if ($corrupted) {
                $counts['corrupt']++;
                $this->warn("Bozuk/erişilemez girdi görseli nedeniyle atlandı: generation {$generation->id}");

                continue;
            }

            $line = [
                'generation_id'    => $generation->id,
                'product_category' => $generation->product?->category,
                'inputs'           => $inputs,
                'output'           => ['local_path' => $outputLocal],
                'prompt'           => $generation->rendered_prompt,
                'prompt_version'   => $generation->promptVersion?->version_label,
                'verdict'          => $review->verdict,
                'reason_code'      => $review->reason_code,
                'reason_text'      => $review->reason_text,
                'severity'         => $review->severity,
            ];

            fwrite($manifest, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
            $counts['exported']++;
        }

        fclose($manifest);

        $this->line(json_encode($counts, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /**
     * @param  array<string,string>  $downloaded  asset_id => local_path (bellek-içi cache; aynı
     *         asset birden çok manifest satırında referanslanabilir, yeniden indirilmez)
     */
    private function ensureLocal(Asset $asset, string $assetsDir, TrainingStorageService $storage, array &$downloaded): ?string
    {
        if (isset($downloaded[$asset->id])) {
            return $downloaded[$asset->id];
        }

        $ext = strtolower(pathinfo($asset->storage_key, PATHINFO_EXTENSION)) ?: 'bin';
        $local = "{$assetsDir}/{$asset->id}.{$ext}";

        try {
            $storage->download($asset, $local);
        } catch (\Throwable) {
            return null;
        }

        // Hash doğrulaması: export sırasında bozuk dosya sessizce geçmesin.
        if (hash_file('sha256', $local) !== $asset->content_sha256) {
            @unlink($local);

            return null;
        }

        return $downloaded[$asset->id] = $local;
    }
}
