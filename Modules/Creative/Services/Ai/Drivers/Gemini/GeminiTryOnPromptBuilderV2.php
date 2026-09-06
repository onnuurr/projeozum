<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\TryOnPromptBuilderContract;

/**
 * SİLÜET HİPOTEZİ deney sürümü — tryon-prompt-compare A/B ölçüm yüzeyi için.
 *
 * TEK DEĞİŞKEN: {@see GeminiTryOnPromptBuilder::build()}'daki "Keep the person
 * strictly consistent..." satırından yalnızca "body type" kimlik-kilidi
 * çıkarıldı ve gövde/torso siluetinin giysinin hacmine göre değişmesine AÇIKÇA
 * izin veren bir cümle eklendi. Hipotez: mevcut prompt kişinin "body type"ını
 * kimlikle birlikte katı biçimde sabitlediği için, üretici model dış giyim gibi
 * hacimli ürünlerde gövdeyi GENİŞLETMİYOR — giysiyi var olan (ince) siluetin
 * üzerine "yapıştırıyor" (bkz. proje hafızası + PromptDirectives::fabric()
 * docblock'undaki "yapıştırılmış" terimi, orada kastedilen kumaş dokusu/AO
 * idi; burada kastedilen GÖVDE HACMİ).
 *
 * Bu dosya, V1'in DONDURULMUŞ bir kopyasıdır — kalıcı üretim kodu DEĞİLDİR,
 * sadece {@see \Modules\Creative\Console\Commands\TryonPromptCompareCommand}
 * ile A/B ölçmek için var. Satır 49 (identity-lock cümlesi) dışında V1 ile
 * KARAKTER KARAKTER aynıdır — bkz. `php artisan creative:tryon-prompt-compare
 * --mode=text --b=...GeminiTryOnPromptBuilderV2` çıktısındaki diff, bunun tek
 * satırlık bir fark olduğunu doğrular. Hipotez doğrulanır ve prompt
 * kalıcılaştırılırsa bu dosya silinip değişiklik V1'e taşınır; doğrulanmazsa
 * bu dosya silinir — kalıcı bir ikinci sürüm olarak YAŞAMAMALI.
 */
class GeminiTryOnPromptBuilderV2 implements TryOnPromptBuilderContract
{
    public function build(array $extras = [], ?string $extraInstruction = null, ?string $protectListSentence = null): string
    {
        $lines = [];

        if ($protectListSentence !== null && trim($protectListSentence) !== '') {
            $lines[] = trim($protectListSentence);
        }

        $lines = [
            ...$lines,
            'You are given two or more images. The FIRST image is a PERSON (a model in a specific pose).',
            'The SECOND image is the main photo of a GARMENT/PRODUCT (a piece of clothing or wearable item).',
            'Task: dress the person from the first image in the garment shown in the second (and any further) image — a virtual try-on.',
            // === TEK DEĞİŞKEN (siluet hipotezi) — bu, V1'deki satır 49 ile aynı
            // konumdaki TEK farklı satır. "body type" kimlik-kilidinden çıkarıldı;
            // gövde/torso siluetinin giysinin hacmine göre GÖRÜNÜR şekilde
            // değişmesine izin veren açık bir istisna cümlesi eklendi. Kimlik
            // (yüz/saç/ten), poz, çerçeve, ışık, arka plan aynen V1'deki gibi kilitli
            // kalıyor — sadece "body type" kelimesi bu listeden çıkarıldı.
            "Keep the person's identity strictly consistent with the first image: same face, hairstyle, skin tone, exact pose, body orientation, framing, lighting and background — do not change the person's identity or the scene. The torso/body silhouette is the ONE exception: let it visibly change shape according to the volume, thickness and structure of the garment being worn (e.g. a bulky, padded or structured garment should visibly add bulk and change the outline; a fitted garment should visibly follow the body) — do not force the pre-existing body silhouette to stay unchanged underneath the new garment.",
            'Replace the person\'s current base clothing with the product so it is naturally worn on the correct body region (top, bottom, dress, outerwear, etc.).',
            'Preserve the product\'s real cut, silhouette, colors, fabric, texture, patterns, prints, logos, branding and any text EXACTLY as shown — do not redesign, recolor, restyle or invent a different product.',
            'The garment\'s color, hue and saturation must render as colorimetrically identical to its reference photo — apply absolutely no color grading, white balance shift, warm/cool tint or saturation adjustment to the garment fabric itself, regardless of the scene\'s ambient lighting color.',
            'All garment hardware and trim — zippers, zipper pulls, zipper tape/bands, buttons, snaps, drawstrings, eyelets — including their exact count, layout and color, must be reproduced identically to the reference photo; never add, remove, duplicate or recolor any hardware element (e.g. do not turn a single-band zipper into a double band, do not change the tape or trim color).',
        ];

        if ($extras !== []) {
            $lines[] = $this->describeExtras($extras);
        }

        $lines[] = 'The collar, neckline and front-facing chest area must show ONLY the FRONT design of the garment as seen in its main photo — never the back print, an inside hang tag, a size label or the lining fabric.';
        $lines[] = 'Make the garment fit, drape and fold realistically on the body with correct shadows and contact, matching the first image\'s lighting.';
        $lines[] = PromptDirectives::fabric();
        $lines[] = PromptDirectives::realism();
        $lines[] = PromptDirectives::camera();
        $lines[] = 'Add soft, accurate contact shadows beneath the footwear that ground the model to the floor.';
        $lines[] = 'The person\'s limbs, head-to-body ratio and overall body proportions must exactly match the first image — sleeves and cuffs must follow the model\'s true arm length and attach continuously and naturally at the shoulder, elbow and wrist with no gap, disconnection or floating fabric; never lengthen, shorten, or distort a limb, the neck or the head to fit the garment.';
        $lines[] = 'Single person, full body visible from head to feet, centered, sharp focus, correct anatomy, hands and proportions, no text or watermark added.';

        if ($extraInstruction !== null && trim($extraInstruction) !== '') {
            $lines[] = 'IMPORTANT correction: the previous attempt was rejected for this exact reason, fix it in this generation: ' . trim($extraInstruction);
        }

        return implode(' ', $lines);
    }

    /**
     * V1'deki {@see GeminiTryOnPromptBuilder::describeExtras()} ile birebir aynı
     * (private olduğu için miras alınamıyor, bilerek kopyalandı — tek değişken
     * kuralı burada da geçerli: bu metotta HİÇBİR fark yok).
     *
     * @param  array<int,array{label:?string,statement?:?string}>  $extras
     */
    private function describeExtras(array $extras): string
    {
        $descriptions = [];
        $statements   = [];
        foreach (array_values($extras) as $i => $extra) {
            $label   = $extra['label'] ?? null;
            $ordinal = $i + 3;
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
