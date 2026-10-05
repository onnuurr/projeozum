<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\MannequinPoseRequest;

/**
 * MannequinPoseRequest'ten Gemini poz prompt'u üretir.
 *
 * Kritik: referans görseldeki kişinin KİMLİĞİ (yüz yapısı, ten, saç, vücut, taban
 * kıyafet) korunur; yalnızca duruş değiştirilir. İstisna — kombin tamamlama
 * (stylingGarmentPath verilmişse): taban kıyafet yerine ürüne uygun bir kombin
 * giydirilir, ürünün bölgesine ise sade bir yer tutucu konur (bkz. stylingLines()).
 * Arka plan/çerçeve tüm pozlarda
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
        $styling    = $request->stylingGarmentPath !== null;

        $lines = [
            'You are given reference images. The FIRST image is the PERSON (identity source).',
            $styling
                ? 'Keep the person\'s facial identity strictly consistent with the first image: same bone structure, eyes, nose, mouth shape, hairstyle, skin tone and body type — do not change who this person is. Their clothing WILL change as described below.'
                : 'Keep the person\'s facial identity strictly consistent with the first image: same bone structure, eyes, nose, mouth shape, hairstyle, skin tone, body type and the same plain base clothing — do not change who this person is or their outfit.',
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

        if ($styling) {
            array_push($lines, ...$this->stylingLines($hasPoseRef ? 3 : 2));
        }

        $lines[] = 'Full-body framing, the entire body visible from head to feet, single person centered.';
        $lines[] = 'Plain seamless light-gray studio background with soft even lighting, no props, no text or watermark.';
        // Anti-AI gerçekçilik çapası (config toggle'a duyarlı) — üç aşamada tutarlı.
        $lines[] = PromptDirectives::realism();
        $lines[] = PromptDirectives::camera();

        return implode(' ', $lines);
    }

    /**
     * Kombin tamamlama: ürün (ör. etek) tek başına satılıyor ama katalog fotoğrafı kombinli
     * olmalı — eksik parçaları (üst, ayakkabı, çorap, sade aksesuar) AI o anki kararıyla
     * ürüne uygun seçer. Ürünün KENDİ bölgesine bu adımda ürün çizilmez; aynı tipte sade bir
     * yer tutucu konur ve gerçek ürün sonraki try-on adımında (Gemini ya da fal) giydirilir —
     * ürün pikselleri en son adımdan geldiği için ürün sadakati bozulmaz.
     *
     * @return array<int,string>
     */
    private function stylingLines(int $garmentImageNo): array
    {
        return [
            sprintf('Image #%d is a PRODUCT (a garment that is sold on its own). It will be put on this person in a LATER step — in THIS image do NOT draw the product itself.', $garmentImageNo),
            'Replace the person\'s plain base clothing with a complete, coordinated, realistic e-commerce catalog outfit built AROUND that product: decide which body region the product covers (e.g. top, bottom, dress/one-piece, outerwear, footwear) and style every OTHER region with complementary pieces you choose — e.g. a matching top for a skirt or trousers, a matching bottom for a top, suitable footwear (heels, flats, sneakers or boots as fits the product), sheer tights or socks when they suit the look, and at most one subtle accessory.',
            'The complementary pieces must make the product the hero: harmonizing or neutral solid colors that suit the product\'s colors and style, simple real-world garments, no logos, text, graphics or bold prints, and never repeat the product\'s own pattern or print on them.',
            'On the product\'s own body region, dress the person in a simple, plain, close-fitting placeholder of the SAME garment type with a similar length and silhouette (e.g. a plain skirt of similar length for a skirt) in a neutral solid color — it will be replaced by the real product later.',
            'Nothing may be layered over or hide the product\'s region (no jacket, coat, scarf, bag or hand covering it) unless the product itself is outerwear; if the product is a dress or one-piece, only add footwear and at most one subtle accessory.',
        ];
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
