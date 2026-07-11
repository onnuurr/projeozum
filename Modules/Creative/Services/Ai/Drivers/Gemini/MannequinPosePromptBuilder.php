<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\MannequinPoseRequest;

/**
 * MannequinPoseRequest'ten Gemini poz prompt'u üretir.
 *
 * Kritik: referans görseldeki kişinin KİMLİĞİ (yüz, ten, saç, vücut, taban kıyafet)
 * korunur; yalnızca duruş değiştirilir. Arka plan/çerçeve tüm pozlarda tutarlı tutulur
 * ki sonraki try-on aşaması temiz bir "model" görseli alsın.
 */
class MannequinPosePromptBuilder
{
    public function build(MannequinPoseRequest $request): string
    {
        if ($request->promptOverride) {
            return $request->promptOverride;
        }

        $hasPoseRef = $request->posePreviewPath !== null;

        $lines = [
            'You are given reference images. The FIRST image is the PERSON (identity source).',
            'Keep the identity strictly consistent with the first image: same face, hairstyle, skin tone, body type and the same plain base clothing — do not change the person or outfit.',
        ];

        if ($hasPoseRef) {
            // İkinci görsel poz şablonudur; duruş birebir ondan kopyalanır.
            $lines[] = 'The SECOND image is a POSE TEMPLATE (a neutral mannequin). Reproduce its body pose EXACTLY: same stance, limb angles, body orientation, head direction and framing.';
            $lines[] = sprintf('Pose description for reference: %s.', $request->directive);
            $lines[] = 'Output the person from the first image performing the exact pose of the second image. Ignore the second image\'s appearance/identity — copy only its pose.';
        } else {
            $lines[] = sprintf('Re-render this same person in a new pose: %s.', $request->directive);
        }

        $lines[] = 'Full-body framing, the entire body visible from head to feet, single person centered.';
        $lines[] = 'Plain seamless light-gray studio background with soft even lighting, no props, no text or watermark.';
        // Anti-AI gerçekçilik çapası (config toggle'a duyarlı) — üç aşamada tutarlı.
        $lines[] = PromptDirectives::realism();

        return implode(' ', $lines);
    }
}
