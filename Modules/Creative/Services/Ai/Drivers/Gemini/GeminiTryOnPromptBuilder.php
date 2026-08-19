<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

/**
 * Gemini try-on (giydirme) prompt'u üretir.
 *
 * Kritik: BİRİNCİ görseldeki kişinin kimliği, duruşu, çerçevesi ve arka planı
 * birebir korunur; yalnızca üzerindeki taban kıyafet, İKİNCİ görseldeki ürünle
 * (giysiyle) değiştirilir. Ürünün gerçek kesim/renk/kumaş/desen/marka/yazı
 * detayları aynen taşınır — yeniden tasarlanmaz, başka ürün uydurulmaz.
 *
 * $extras artık salt etiket değil, {@see \Modules\Creative\Services\GarmentIdentityRuleEngine}'in
 * ürettiği confidence-filtrelenmiş direktifleri de taşıyabilir (statement/
 * priority/protect) — bkz. ROADMAP.md Faz G.5. Bu alanlar boş/yoksa (analiz
 * kapalı, eski usul manuel yükleme) davranış G.1-G.3'teki gibi AYNEN kalır.
 */
class GeminiTryOnPromptBuilder
{
    /**
     * @param  array<int,array{path?:string,label:?string,statement?:?string,priority?:?string,protect?:bool}>  $extras
     *         Ana giysi görselinden SONRA gelen ek detay görselleri (varsa) —
     *         sırayla 3., 4., ... görsel. `label` boşsa genel "additional
     *         angle/detail" ifadesi kullanılır. `statement` doluysa (Rule
     *         Engine'den) o parça için ek, somut bir koruma talimatı eklenir.
     * @param  ?string  $extraInstruction  Ret sonrası sohbetten çıkan düzeltme talimatı
     *         (bkz. ReviewChatService) — verilmişse prompt'un sonuna, önceki denemedeki
     *         somut sorunu hedefleyen bir düzeltme cümlesi olarak eklenir.
     * @param  ?string  $protectListSentence  {@see \Modules\Creative\Services\GarmentIdentityRuleEngine::protectListSentence()}
     *         çıktısı — verilmişse prompt'un EN BAŞINA bir kimlik-koruma çapası olarak eklenir.
     */
    public function build(array $extras = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string
    {
        $lines = [];

        // Kimlik-koruma çapası EN BAŞA gider — üretici modelin dikkatini
        // gerçekten korunması gereken ayrıntılara ilk cümlede çeker.
        if ($protectListSentence !== null && trim($protectListSentence) !== '') {
            $lines[] = trim($protectListSentence);
        }

        $lines = [
            ...$lines,
            'You are given two or more images. The FIRST image is a PERSON (a model in a specific pose).',
            'The SECOND image is the main photo of a GARMENT/PRODUCT (a piece of clothing or wearable item).',
            'Task: dress the person from the first image in the garment shown in the second (and any further) image — a virtual try-on.',
            'Keep the person strictly consistent with the first image: same face, hairstyle, skin tone, body type, exact pose, body orientation, framing, lighting and background. Do not change the person or the scene.',
            'Replace the person\'s current base clothing with the product so it is naturally worn on the correct body region (top, bottom, dress, outerwear, etc.).',
            'Preserve the product\'s real cut, silhouette, colors, fabric, texture, patterns, prints, logos, branding and any text EXACTLY as shown — do not redesign, recolor, restyle or invent a different product.',
            // Renk sadakati (Faz Q): rengi İSİMLENDİRMEDEN (ör. "lacivert") negatif bir
            // kısıt olarak veriyoruz — isim vermek modelin kendi yorumunu (ör. kendi
            // "lacivert" tonunu) üretmesine yol açar. Sahne ışığı sıcak/soğuk olsa bile
            // (bkz. PromptDirectives::camera()) giysinin rengi buna uydurulmasın.
            'The garment\'s color, hue and saturation must render as colorimetrically identical to its reference photo — apply absolutely no color grading, white balance shift, warm/cool tint or saturation adjustment to the garment fabric itself, regardless of the scene\'s ambient lighting color.',
        ];

        if ($extras !== []) {
            $lines[] = $this->describeExtras($extras);
        }

        // Yaka/yakada arka baskı, etiket veya astar görünmesi (tekrarlanan bir hata modu):
        // ön/arka karışıklığını açıkça yasakla.
        $lines[] = 'The collar, neckline and front-facing chest area must show ONLY the FRONT design of the garment as seen in its main photo — never the back print, an inside hang tag, a size label or the lining fabric.';
        $lines[] = 'Make the garment fit, drape and fold realistically on the body with correct shadows and contact, matching the first image\'s lighting.';
        // Kumaş mühendisliği: giysinin "yapıştırılmış" durmasını önler (ambient occlusion).
        $lines[] = PromptDirectives::fabric();
        // Anti-AI gerçekçilik çapası (config toggle'a duyarlı).
        $lines[] = PromptDirectives::realism();
        $lines[] = PromptDirectives::camera();
        $lines[] = 'Add soft, accurate contact shadows beneath the footwear that ground the model to the floor.';
        $lines[] = 'Single person, full body visible from head to feet, centered, sharp focus, correct anatomy, hands and proportions, no text or watermark added.';

        if ($extraInstruction !== null && trim($extraInstruction) !== '') {
            // Önceki denemede bu YÜZDEN reddedildi — modele en son ve en belirgin talimat
            // olarak veriyoruz ki aynı hatayı tekrarlamasın.
            $lines[] = 'IMPORTANT correction: the previous attempt was rejected for this exact reason, fix it in this generation: ' . trim($extraInstruction);
        }

        return implode(' ', $lines);
    }

    /**
     * @param  array<int,array{label:?string,statement?:?string}>  $extras
     */
    private function describeExtras(array $extras): string
    {
        $descriptions = [];
        $statements   = [];
        foreach (array_values($extras) as $i => $extra) {
            $label   = $extra['label'] ?? null;
            $ordinal = $i + 3; // 1=person, 2=main garment photo, 3.. = extras
            $descriptions[] = $label
                ? sprintf('image #%d is the SAME garment, "%s" view', $ordinal, $label)
                : sprintf('image #%d is the SAME garment from an additional angle/detail', $ordinal);

            $statement = trim((string) ($extra['statement'] ?? ''));
            if ($statement !== '') {
                $statements[] = $statement;
            }
        }

        $base = 'You are ALSO given additional reference images of the SAME garment ('
            . implode('; ', $descriptions)
            . ') — these are NOT separate garments. Use them ONLY to understand unseen construction '
            . '(back/side seams, stitching, hardware, collar closure) on parts the main photo does not '
            . 'show. CRITICAL: never copy the print, pattern, graphic or color placement from a back/side '
            . 'reference onto a front-facing area (especially the collar, neckline and chest) — the front '
            . 'of the garment as rendered must match ONLY what the MAIN (first garment) photo shows there.';

        if ($statements === []) {
            return $base;
        }

        return $base . ' Specific preservation instructions for the reference details above: ' . implode(' ', $statements);
    }
}
