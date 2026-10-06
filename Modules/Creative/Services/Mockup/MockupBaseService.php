<?php

namespace Modules\Creative\Services\Mockup;

use App\Support\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupBase;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Models\Pose;
use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Contracts\MockupVisionContract;
use Modules\Creative\Services\Ai\Support\AiUsageRecorder;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * Kombinin baz görsellerini üretir: önce ana kare (manken kimliği + ana poz), sonra stilistin
 * seçtiği pozlar ana kareden türetilir (kombin/aksesuar pozlar arası aynı kalır). Her baz
 * segmente edilir ve bölge maskeleri çıkarılır. Dosyalar iş diskinde (herkese açık değil):
 * {work_dir}/{product}/outfit_{id}/{pose_key}.png | _labels.npy | _region.png ...
 */
class MockupBaseService
{
    public function __construct(
        private MockupGeneratorContract $generator,
        private MockupVisionContract $vision,
    ) {}

    /** @return Collection<int,MockupBase> hazır bazlar (ana kare ilk sırada) */
    public function generate(MockupOutfit $outfit): Collection
    {
        $mannequin = $outfit->mannequin;
        if (! $mannequin || $mannequin->status !== Mannequin::STATUS_READY || ! $mannequin->reference_image_path) {
            throw new RuntimeException(__('creative::messages.mockup.mannequin_not_ready'));
        }

        $masterPose = Pose::where('pose_key', config('creative.mockup.master_pose'))->where('status', Pose::STATUS_READY)->first();
        if (! $masterPose) {
            throw new RuntimeException(__('creative::messages.mockup.no_master_pose'));
        }
        $others = Pose::whereIn('pose_key', (array) $outfit->poses)->where('status', Pose::STATUS_READY)->get();

        $spec = [
            'sentence' => (string) $outfit->outfit_sentence,
            'summary'  => (string) ($outfit->meta['product_summary'] ?? ''),
            'region'   => $outfit->region,
            'length'   => $outfit->length,
        ];

        $master = $this->buildChecked($outfit, $masterPose, null, fn ($poseTpl) => $this->generator->masterBase(
            $this->local($mannequin->reference_image_path), $poseTpl, $spec,
        ));
        if ($master->status !== MockupBase::STATUS_READY) {
            throw new RuntimeException(__('creative::messages.mockup.master_failed', ['error' => $master->error]));
        }

        $ready = collect([$master]);
        $masterPath = $this->disk()->path($master->image_path);
        foreach ($others as $pose) {
            $base = $this->buildChecked($outfit, $pose, $master, fn ($poseTpl) => $this->generator->derivedBase($masterPath, $poseTpl, $spec));
            if ($base->status === MockupBase::STATUS_READY) {
                $ready->push($base);
            }
        }

        return $ready;
    }

    /**
     * Faz 2 baz kontrolü ile üretim: ayakkabı (ücretsiz, segmentasyondan) + türetilen pozda ana kareyle
     * tutarlılık (analiz çağrısı). Kalırsa EN FAZLA qa.max_retries kez yeniden üretilir; yine kalırsa
     * baz "warn" ile kullanılır (bulgular giydirme sonucuna taşınır, onaycı görür).
     *
     * @param  callable(string):string  $render
     */
    private function buildChecked(MockupOutfit $outfit, Pose $pose, ?MockupBase $master, callable $render): MockupBase
    {
        $enabled = (bool) config('creative.mockup.qa.enabled', true);
        $max = $enabled ? max(0, (int) config('creative.mockup.qa.max_retries', 1)) : 0;

        for ($try = 0; ; $try++) {
            $base = $this->build($outfit, $pose, $master, $render);
            if ($base->status !== MockupBase::STATUS_READY || ! $enabled) {
                return $base;
            }

            $qa = $this->check($base, $master);
            $base->update(['qa' => [...$qa, 'retries' => $try]]);
            if (! $qa['retry'] || $try >= $max) {
                return $base->fresh();
            }
        }
    }

    /** @return array{status:string, retry:bool, shoes:array<string,mixed>, consistency:?array<string,mixed>} */
    private function check(MockupBase $base, ?MockupBase $master): array
    {
        $skin = (float) ($base->masks['checks']['shoe_skin_ratio'] ?? 0);
        $shoesOk = $skin <= (float) config('creative.mockup.qa.max_shoe_skin_ratio', 0.6);

        $consistency = null;
        if ($master !== null) {
            $pair = rtrim(sys_get_temp_dir(), '/') . '/mockup_pair_' . bin2hex(random_bytes(6)) . '.jpg';
            try {
                $this->vision->pair($this->disk()->path($master->image_path), $this->disk()->path($base->image_path), $pair);
                $consistency = app(AiUsageRecorder::class)->forSubject($base, fn () => $this->generator->checkBaseConsistency($pair));
            } catch (\Throwable $e) {
                // Kontrol başarısızlığı üretimi durdurmaz; onaycı "bilinmiyor" görür.
                $consistency = ['verdict' => 'unknown', 'issues' => [['detail_tr' => 'Tutarlılık kontrolü yapılamadı: ' . $e->getMessage()]]];
            } finally {
                ImageFile::delete([$pair]);
            }
        }

        // fail → yeniden üretim; warn (küçük aksesuar vb.) / unknown → yeniden üretim YOK, onaycıya uyarı.
        $verdict = $consistency['verdict'] ?? 'pass';
        $failed = ! $shoesOk || $verdict === 'fail';

        return [
            'status'      => $failed || in_array($verdict, ['warn', 'unknown'], true) ? 'warn' : 'pass',
            'retry'       => $failed,
            'shoes'       => ['skin_ratio' => $skin, 'ok' => $shoesOk],
            'consistency' => $consistency,
        ];
    }

    /** @param callable(string):string $render poz şablonu yolu → üretilen geçici görsel */
    private function build(MockupOutfit $outfit, Pose $pose, ?MockupBase $master, callable $render): MockupBase
    {
        $base = MockupBase::updateOrCreate(
            ['outfit_id' => $outfit->id, 'pose_id' => $pose->id],
            ['derived_from_base_id' => $master?->id, 'status' => MockupBase::STATUS_GENERATING, 'error' => null],
        );
        $base->increment('attempts');

        $dir = trim((string) config('creative.mockup.work_dir', 'creative/mockup'), '/') . "/{$outfit->product_id}/outfit_{$outfit->id}";
        $rel = "{$dir}/{$pose->pose_key}";
        $tmp = null;

        try {
            $tmp = app(AiUsageRecorder::class)->forSubject($base, fn () => $render($this->local($pose->preview_image_path)));
            $this->disk()->makeDirectory($dir);
            // Düşük çözünürlükte (1K) üretilen baz, sunucuda ücretsiz büyütülüp saklanır.
            $this->vision->upscale($tmp, $this->disk()->path("{$rel}.png"),
                (int) config('creative.mockup.base_width', 1792), (int) config('creative.mockup.base_height', 2400));

            $seg  = $this->vision->segment($this->disk()->path("{$rel}.png"), $this->disk()->path("{$rel}_labels.npy"));
            $mask = $this->vision->mask($this->disk()->path("{$rel}_labels.npy"), $this->disk()->path($rel), $outfit->region, $outfit->length);

            $base->update([
                'image_path' => "{$rel}.png",
                'masks'      => [
                    'prefix' => $rel,
                    'crop'   => array_map('intval', $mask['crop']),
                    'long'   => (bool) ($mask['long'] ?? false),
                    'labels' => $seg['labels'] ?? null,
                    'checks' => $seg['checks'] ?? null,
                ],
                'status'     => MockupBase::STATUS_READY,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mokap baz görseli üretilemedi', ['outfit_id' => $outfit->id, 'pose' => $pose->pose_key, 'error' => $e->getMessage()]);
            $base->update(['status' => MockupBase::STATUS_FAILED, 'error' => $e->getMessage()]);
        } finally {
            ImageFile::delete([$tmp]);
        }

        return $base->fresh();
    }

    private function local(?string $path): string
    {
        $local = $path ? Media::localPath($path) : null;
        if (! $local || ! is_file($local)) {
            throw new RuntimeException("Görsel bulunamadı: {$path}");
        }

        return $local;
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk((string) config('creative.mockup.work_disk', 'local'));
    }
}
