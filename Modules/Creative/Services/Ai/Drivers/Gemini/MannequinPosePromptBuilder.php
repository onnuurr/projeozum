<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\MannequinPoseRequest;

/**
 * MannequinPoseRequest'ten Gemini poz prompt'u üretir.
 *
 * Kritik: referans görseldeki kişinin KİMLİĞİ (yüz yapısı, ten, saç, vücut, taban
 * kıyafet) korunur; yalnızca duruş değiştirilir. Arka plan/çerçeve tüm pozlarda
 * tutarlı tutulur ki sonraki try-on aşaması temiz bir "model" görseli alsın.
 *
 * Yüz İFADESİ kimliğin dışında tutulur: her poz üretiminde rastgele bir ifade
 * varyantı (bkz. config creative.ai.prompt.pose_expressions) seçilip prompt'a
 * eklenir. compose() her ürün×poz için ayrı çalıştığından bu, aynı mankenin
 * bütün pozlarda/ürünlerde birebir donmuş aynı ifadeyle çıkmasını (belirgin bir
 * "AI" işareti) önler — gerçek bir çoklu-kare stüdyo çekiminde olduğu gibi
 * ifade kareden kareye doğal biçimde değişir, kimlik değişmez.
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
            'Keep the person\'s facial identity strictly consistent with the first image: same bone structure, eyes, nose, mouth shape, hairstyle, skin tone, body type and the same plain base clothing — do not change who this person is or their outfit.',
            sprintf(
                'This frame is one shot from a real multi-shot studio session with the same model: do NOT copy the exact frozen facial expression from the reference photo verbatim — instead render a naturally different, believable expression for this specific shot: %s. A real person never holds an identical micro-expression across dozens of photos; the eyes, mouth and gaze must show subtle natural variation from shot to shot while the underlying face stays unmistakably the same person.',
                $this->expressionVariant(),
            ),
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
        $lines[] = PromptDirectives::camera();

        return implode(' ', $lines);
    }

    /**
     * config('creative.ai.prompt.pose_expressions') havuzundan rastgele bir ifade
     * varyantı seçer. Havuz boşsa/eksikse nötr bir varsayılana düşer.
     */
    private function expressionVariant(): string
    {
        $pool = array_values(array_filter(
            (array) config('creative.ai.prompt.pose_expressions', []),
            fn ($v) => is_string($v) && trim($v) !== '',
        ));

        if ($pool === []) {
            return 'a natural, relaxed expression with a soft, closed-mouth smile';
        }

        return $pool[array_rand($pool)];
    }
}
