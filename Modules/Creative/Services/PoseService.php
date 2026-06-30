<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Pose;
use Modules\Creative\Services\Ai\Contracts\PosePreviewComposerContract;
use Modules\Creative\Services\Ai\PoseRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * Bağımsız poz kütüphanesi orchestrator'ı.
 *
 * seed(): config kataloğunu creative_poses'a işler (idempotent).
 * generate(): bir poz için nötr figür önizlemesi üretir ve diske yazar.
 */
class PoseService
{
    public function __construct(
        private PosePreviewComposerContract $composer,
    ) {}

    /**
     * Config poz kataloğunu kütüphaneye işler (varsa korur, eksikleri ekler).
     *
     * @return Collection<int,Pose>
     */
    public function seed(): Collection
    {
        $catalog = (array) config('creative.mannequin.poses', []);

        return collect($catalog)->values()->map(function (array $entry, int $i) {
            $pose = Pose::firstOrNew(['pose_key' => (string) $entry['key']]);
            $pose->label      = $entry['label'] ?? $entry['key'];
            $pose->prompt     = $entry['prompt'] ?? '';
            $pose->sort_order = $i;
            // Yeni eklenenler 'draft'; mevcutların durumu/önizlemesi korunur.
            if (! $pose->exists) {
                $pose->status = Pose::STATUS_DRAFT;
            }
            $pose->save();

            return $pose;
        });
    }

    /**
     * Bir poz için nötr figür önizlemesi üretir, diske yazar, pozu 'ready' işaretler.
     */
    public function generate(Pose $pose): Pose
    {
        $request = new PoseRequest(
            label:     (string) ($pose->label ?: $pose->pose_key),
            directive: (string) $pose->prompt,
        );

        $temp = null;

        try {
            $temp = $this->composer->compose($request);
            $rel  = $this->persist($temp, $pose);

            $pose->fill([
                'preview_image_path' => $rel,
                'status'             => Pose::STATUS_READY,
                'error'              => null,
            ])->save();

            return $pose;
        } finally {
            ImageFile::delete([$temp]);
        }
    }

    private function persist(string $sourcePath, Pose $pose): string
    {
        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Poz önizleme görseli okunamadı.');
        }

        $disk = config('creative.disk', 'public');
        $rel  = sprintf('%s/poses/%s.png', config('creative.mannequin.output_dir', 'mannequins'), $pose->pose_key);

        Storage::disk($disk)->put($rel, $bytes);

        return $rel;
    }
}
