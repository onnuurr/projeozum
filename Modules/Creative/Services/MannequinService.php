<?php

namespace Modules\Creative\Services;

use App\Support\Media;
use Illuminate\Support\Facades\Storage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Services\Ai\Contracts\MannequinComposerContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\MannequinPromptBuilder;
use Modules\Creative\Services\Ai\MannequinRequest;
use Modules\Creative\Services\Ai\Support\ImageFile;
use Modules\Creative\Services\Enhancement\ImageEnhancerContract;
use RuntimeException;

/**
 * Sanal manken kimlik görseli üretim orchestrator'ı.
 *
 * Model tarifinden bir referans görsel kurgular (compose), kalıcı diske yazar
 * ve mankeni 'ready' işaretler. Ara çıktı (geçici dosya) her durumda temizlenir.
 * Üretim bitince görsel insan onayına düşer (review_status=pending) — kimlik
 * onaylanana kadar giydirme aşamasında seçilebilir olmaz.
 */
class MannequinService
{
    public function __construct(
        private MannequinComposerContract $composer,
        private MannequinPromptBuilder $prompts,
        private ReviewNotifier $notifier,
        private ImageEnhancerContract $enhancer,
    ) {}

    public function generate(Mannequin $mannequin): Mannequin
    {
        $request = $this->toRequest($mannequin);
        // Üretilen prompt izlenebilirlik için saklanır; aynısı compose'a verilir.
        $prompt  = $this->prompts->build($request);
        $request->promptOverride = $prompt;

        $temp     = null;
        $enhanced = null;

        try {
            $temp = $this->composer->compose($request);

            // Kimlik görseline de üretim sonrası iyileştirme (upscale + son dokunuş)
            // uygulanır — try-on/şablon ile tutarlı. Kapalıysa passthrough ($temp döner).
            $enhanced = $this->enhancer->enhance($temp);
            $rel      = $this->persist($enhanced, $mannequin);

            $mannequin->fill([
                'prompt'               => $prompt,
                'reference_image_path' => $rel,
                'status'               => Mannequin::STATUS_READY,
                'error'                => null,
                'review_status'        => Mannequin::REVIEW_PENDING,
                'review_note'          => null,
                'reviewed_by'          => null,
                'reviewed_at'          => null,
            ])->save();

            $this->notifier->notifyPending($mannequin, $mannequin->created_by);

            return $mannequin;
        } finally {
            // $enhanced === $temp olabilir (passthrough); delete idempotenttir.
            ImageFile::delete([$temp, $enhanced]);
        }
    }

    private function toRequest(Mannequin $mannequin): MannequinRequest
    {
        return new MannequinRequest(
            name:     (string) $mannequin->name,
            gender:   $mannequin->gender,
            ageRange: $mannequin->age_range,
            skinTone: $mannequin->skin_tone,
            bodyType: $mannequin->body_type,
            hair:     $mannequin->hair,
            face:     $mannequin->face,
            heightCm: $mannequin->height_cm,
            bustCm:   $mannequin->bust_cm,
            waistCm:  $mannequin->waist_cm,
            hipsCm:   $mannequin->hips_cm,
            extras:   $mannequin->extras,
            referencePhotoPath: Media::localPath($mannequin->source_photo_path, config('creative.disk', 'public')),
        );
    }

    /**
     * Referans görseli kalıcı diske yazar, disk göreli yolu döndürür.
     */
    private function persist(string $sourcePath, Mannequin $mannequin): string
    {
        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Manken görseli okunamadı.');
        }

        $encoded = ImageFile::encode(
            $bytes,
            (string) config('creative.image_output.format', 'webp'),
            (int) config('creative.image_output.quality', 90),
        );

        $disk = config('creative.disk', 'public');
        $rel  = sprintf(
            '%s/%d/reference.%s',
            config('creative.mannequin.output_dir', 'mannequins'),
            $mannequin->id,
            $encoded['ext'],
        );

        // Format (uzantı) değiştiyse eski dosya yetim kalmasın diye önce silinir.
        $old = $mannequin->reference_image_path;
        if ($old && $old !== $rel) {
            Storage::disk($disk)->delete($old);
        }

        Storage::disk($disk)->put($rel, $encoded['bytes']);

        return $rel;
    }
}
