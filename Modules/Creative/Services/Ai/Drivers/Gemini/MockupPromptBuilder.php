<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

/**
 * Mokap giydirme prompt'ları (stilist, ana kare, türetilen poz, giydirme).
 * Metinler 2026-10-05 pilotunda (3 deneme, 5 ürün tipi) doğrulanan hâlleridir.
 * Prompt değişince STYLIST_VERSION elle artırılır (sonuç satırlarına yazılır).
 */
class MockupPromptBuilder
{
    public const STYLIST_VERSION = 2;   // 2: seçenek listesi + kural motoru (Faz 3)

    private const STUDIO = 'Plain seamless light-grey studio background with soft, even, diffused frontal studio lighting and a soft natural floor shadow; no props, no window light patterns, no text or watermark.';

    private const FRAMING = 'Full-body framing, the entire body visible from head to feet with some space above the head and below the feet, single person centered.';

    /** Stilistin işaretleyebileceği ürün özellikleri (poz gereksinimleri bunlarla karşılanır). */
    public const PRODUCT_FEATURES = ['pockets', 'collar', 'buttons', 'zipper', 'belt_loops', 'ruffles', 'print'];

    /**
     * Faz 3: stilist serbest kombin YAZMAZ; her tür için listeden sıralı 3 anahtar seçer. Kombin cümlesini
     * MockupStylistRuleEngine seçeneklerin prompt_en alanlarından kurar.
     *
     * @param array{region:string,length:string,age:?string,gender:?string,poses:array<string,string>,extra_poses:int,options:array<string,array<string,string>>} $c
     *        options: kind => key => kısa İngilizce tanım (yalnız bu ürüne uygun olanlar)
     */
    public function stylist(array $c): string
    {
        $poses = collect($c['poses'])->map(fn ($p, $k) => "- {$k}: {$p}")->implode("\n");
        $options = collect($c['options'])->map(fn ($opts, $kind) => strtoupper($kind) . ":\n" . collect($opts)->map(fn ($d, $k) => "- {$k}: {$d}")->implode("\n"))->implode("\n");
        $kinds = implode(', ', array_map(fn ($k) => "\"{$k}\": [\"key1\",\"key2\",\"key3\"]", array_keys($c['options'])));
        $who = trim(($c['gender'] ? "a {$c['gender']}" : 'a child') . ($c['age'] ? " ({$c['age']})" : ''));
        $features = implode('|', self::PRODUCT_FEATURES);

        return <<<TXT
You are a senior fashion stylist preparing an e-commerce catalog shoot. The model is {$who}.
The attached image is the PRODUCT we sell. It covers the "{$c['region']}" body region (bottom | top | one_piece | set = matching two-piece) and its length is "{$c['length']}" (if "unknown": judge it from the garment's own proportions — mini | knee | midi | maxi; ankle-length is maxi — and return it as "length").
Style a realistic, age-appropriate outfit AROUND the product by choosing ONLY from the option lists below. For every list, return your 3 best keys in order of preference.
- Match the product's character, formality, colors and season (e.g. a romantic tiered piece → a soft knit or blouse with ballet flats or Mary Janes; a sporty denim piece → sneakers; a flowy maxi → sandals or espadrilles). Do not default to white basics unless they are genuinely the best match.
- COLOR is the color of the complementary garment: it must harmonize with the product's palette and must not copy the product's own color.
- Prefer "none" for the accessory unless one clearly improves the look.
Options:
{$options}

Also pick exactly {$c['extra_poses']} poses from the list below that best show THIS product (avoid poses where hands/arms would hide it):
{$poses}

List the product's visible construction features from: {$features}.

Answer ONLY with JSON, no markdown:
{"product_summary": "short English description of the product", "picks": { {$kinds} },
 "poses": ["pose_key"], "features": ["..."], "length": "mini|knee|midi|maxi", "confidence": 0.0-1.0, "reasoning_tr": "kısa Türkçe gerekçe"}
TXT;
    }

    public function baseConsistency(): string
    {
        return 'The LEFT image is the MASTER photo of a photo shoot; the RIGHT image is another pose from the SAME shoot. '
            . 'The pose, camera angle and facial expression are SUPPOSED to differ — never report those. '
            . 'Compare ONLY: the person\'s identity (face), the hairstyle (ponytail vs loose hair, parting, hair accessory), '
            . 'every garment (color, sleeve length, fit, tucked in or not), socks/tights, shoes (and whether the person wears shoes at all), '
            . 'accessories, and the studio background/lighting. '
            . 'Something that simply cannot be seen from one of the two camera angles (e.g. a hair tie at the back of a ponytail, the back of a garment) is NOT a difference. '
            . 'Report ONLY clear contradictions that are visible in BOTH images: loose hair vs ponytail, a different garment or color, different sleeve length, shoes missing or different, an accessory present in one and clearly absent where it should be visible in the other. '
            . 'Answer ONLY JSON: {"verdict":"pass|warn|fail","issues":[{"aspect":"face|hair|top|bottom|socks|shoes|accessory|background","detail_tr":"kısa Türkçe açıklama"}]}. '
            . '"fail" only for clear contradictions in the face, the hairstyle (e.g. loose vs ponytail), any garment, socks/tights or shoes; '
            . 'small accessories (hair ties, clips, jewelry) or background/lighting differences are at most "warn"; otherwise "pass".';
    }

    public function fidelity(string $region, string $view): string
    {
        $views = ['front' => 'from the front', 'side' => 'from the side (profile)', 'back' => 'from the back', 'three_quarter' => 'from a three-quarter angle'];

        return 'The LEFT image is the original product photo (ground truth). The RIGHT image is an AI-generated catalog photo of a model wearing that product '
            . "(the product covers the '{$region}' body region). The model is photographed " . ($views[$view] ?? $views['front']) . '. '
            . 'Compare ONLY the product garment(s), not the model or the other clothes. Check construction details: silhouette and length, number of tiers/seams/panels, '
            . 'pleats/gathers/ruffles, waistband type, collar, sleeves, buttons/closures, pockets, print/pattern scale and colors, color/shade. '
            . 'Details that cannot be seen from this camera angle (e.g. back closures in a front view, front pockets in a back view) must NOT be reported as missing. '
            . 'Natural drape, folds and lighting differences are expected and NOT issues. '
            . 'Answer ONLY JSON: {"verdict":"pass|warn|fail","issues":[{"severity":"minor|major","detail_tr":"kısa Türkçe açıklama","fix_en":"one short English instruction to fix it"}]}. '
            . '"fail" ONLY for STRUCTURAL errors a customer would notice: a construction element added or missing (pocket, button, belt loop, tier, ruffle, seam, collar), '
            . 'a clearly wrong garment type, length or silhouette, a different print/pattern, or a clearly different color (another hue or a dark vs light wash). '
            . 'Shade or brightness differences explainable by studio lighting, pleat/fold crispness, fabric texture and minor proportion differences are at most "warn". '
            . 'If nothing is wrong, answer "pass" with an empty issues list.';
    }

    /** Baz görselde ürünün yerine giyilen sade gri yer tutucu. */
    public function placeholder(string $region, string $length): string
    {
        $long = in_array($length, ['midi', 'maxi'], true);
        $bottom = $long ? 'plain light-grey fitted cotton leggings ending just above the ankle' : 'plain light-grey fitted cotton bike shorts ending at mid-thigh';

        return match ($region) {
            'outerwear' => 'NO outer layer at all (no jacket, coat, vest, cardigan or raincoat) — only the inner outfit described above',
            'top'       => 'a plain light-grey fitted short-sleeve cotton t-shirt, tucked in',
            'set', 'one_piece' => "a plain light-grey fitted short-sleeve cotton t-shirt tucked into {$bottom}",
            default     => $bottom . ', with the top fully tucked into it so the whole waistband is visible',
        };
    }

    public function outfitText(string $outfitSentence, string $productSummary, string $region, string $length): string
    {
        if ($region === 'outerwear') {
            return "Outfit: {$outfitSentence} IMPORTANT: the product itself ({$productSummary}) is NOT drawn in this photo — the person wears "
                . 'NO outer layer at all (no jacket, coat, vest, cardigan or raincoat); it will be added later over this inner outfit.';
        }

        return "Outfit: {$outfitSentence} IMPORTANT: the product itself ({$productSummary}) is NOT drawn in this photo — instead the person wears "
            . $this->placeholder($region, $length) . ' in place of the product.';
    }

    public function masterBase(string $outfitText): string
    {
        return implode(' ', [
            'You are given reference images. The FIRST image is the PERSON (identity source). Keep the person\'s identity strictly consistent: same face, bone structure, eyes, hairstyle, skin tone and body type.',
            'The SECOND image is a POSE TEMPLATE (a neutral mannequin). Reproduce its body pose EXACTLY; ignore its appearance.',
            $outfitText,
            'The person must wear shoes (never barefoot). Nothing else — no other accessories.',
            self::FRAMING, self::STUDIO, PromptDirectives::realism(), PromptDirectives::camera(),
        ]);
    }

    public function derivedBase(string $outfitText): string
    {
        return implode(' ', [
            'You are given reference images. The FIRST image is the MASTER photo from this photo shoot: keep the SAME person (face, hairstyle, skin tone, body), the SAME outfit and accessories EXACTLY (every garment, accessory, socks/tights and shoes — same colors, fit and details; never barefoot), the SAME studio background and the SAME lighting.',
            'The SECOND image is a POSE TEMPLATE (a neutral mannequin). Change ONLY the pose: reproduce its body pose EXACTLY; ignore its appearance.',
            $outfitText,
            self::FRAMING, self::STUDIO, PromptDirectives::realism(), PromptDirectives::camera(),
        ]);
    }

    public function tryOn(string $region, string $length, int $productImageCount, ?string $correction): string
    {
        $long = in_array($length, ['midi', 'maxi'], true);
        $extra = $productImageCount > 1 ? ' Further images are additional reference photos of the SAME product (not separate garments).' : '';
        $task = match ($region) {
            'outerwear' => "The SECOND image is a product photo of OUTERWEAR (a jacket, coat, vest or raincoat).{$extra} Task: put the product ON the person OVER their existing clothes, worn naturally as designed — open or closed exactly as in the product photo, sleeves (if any) covering the arms down to their true length, collar/hood as designed. The clothes underneath stay exactly the same wherever they remain visible." . ($long ? ' The product is long: let it fall to its true length.' : ''),
            'top' => "The SECOND image is a product photo of a TOP.{$extra} Task: replace ONLY the person's plain light-grey placeholder t-shirt with the product top, worn naturally as designed (tucked into the bottom's waistband if it is a blouse); the bottom stays the same.",
            'set' => "The SECOND image is a product photo of a TWO-PIECE SET (a top and a bottom — TWO separate garments).{$extra} Task: replace BOTH of the person's plain light-grey placeholder garments with the set: the set's top on the upper body, the set's bottom on the legs, worn as two separate pieces." . ($long ? ' The long bottom falls naturally to its true length over/around the shoes, which stay the same shoes.' : ''),
            'one_piece' => "The SECOND image is a product photo of a ONE-PIECE garment (dress or jumpsuit).{$extra} Task: replace the person's plain light-grey placeholder garments with the product, worn naturally." . ($long ? ' Let its hem fall to its true length; where it reaches the feet, the fabric falls naturally in front of / around the shoes, which stay the same shoes.' : ''),
            default => "The SECOND image is a product photo of a SKIRT/BOTTOM.{$extra} Task: replace ONLY the person's plain light-grey placeholder bottoms with the product, worn naturally at the waist with the top neatly tucked into its waistband." . ($long ? ' The product is long: let its hem fall to its true length; where it reaches the feet, the fabric falls naturally IN FRONT of / around the shoes, which stay the same shoes.' : ''),
        };

        return implode(' ', array_filter([
            'You are given images. The FIRST image is a cropped photo of a person in a studio.',
            $task,
            'Keep everything else pixel-identical: face, hair, hands, skin, the rest of the outfit, socks, shoes, floor, background and lighting. Do not move, rescale or re-pose anything; keep the exact same framing.',
            'Preserve the product EXACTLY as in the product photo: same color, fabric and print/pattern (same scale, same colors, same placement logic), the same number of tiers/gathers/pleats/ruffles, buttons, collar, sleeves, waistband, pockets, seams, trims and hem — do not redesign, simplify or add anything that is not in the product photo. Natural drape and folds, correct contact shadows.',
            PromptDirectives::realism(),
            $correction ? 'IMPORTANT correction: the previous attempt was rejected by quality control for this exact reason, fix it in this generation: ' . $correction : null,
        ]));
    }
}
