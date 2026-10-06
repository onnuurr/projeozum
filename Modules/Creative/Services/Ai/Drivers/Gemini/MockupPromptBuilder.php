<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

/**
 * Mokap giydirme prompt'ları (stilist, ana kare, türetilen poz, giydirme).
 * Metinler 2026-10-05 pilotunda (3 deneme, 5 ürün tipi) doğrulanan hâlleridir.
 * Prompt değişince STYLIST_VERSION elle artırılır (sonuç satırlarına yazılır).
 */
class MockupPromptBuilder
{
    public const STYLIST_VERSION = 1;

    private const STUDIO = 'Plain seamless light-grey studio background with soft, even, diffused frontal studio lighting and a soft natural floor shadow; no props, no window light patterns, no text or watermark.';

    private const FRAMING = 'Full-body framing, the entire body visible from head to feet with some space above the head and below the feet, single person centered.';

    /** @param array{region:string,length:string,age:?string,gender:?string,poses:array<string,string>,extra_poses:int} $c */
    public function stylist(array $c): string
    {
        $poses = collect($c['poses'])->map(fn ($p, $k) => "- {$k}: {$p}")->implode("\n");
        $who = trim(($c['gender'] ? "a {$c['gender']}" : 'a child') . ($c['age'] ? " ({$c['age']})" : ''));

        return <<<TXT
You are a senior fashion stylist preparing an e-commerce catalog shoot. The model is {$who}.
The attached image is the PRODUCT we sell. It covers the "{$c['region']}" body region (bottom | top | one_piece | set = matching two-piece) and its length is "{$c['length']}" (if "unknown": judge it from the garment's own proportions — mini | knee | midi | maxi; ankle-length is maxi — and return it as "length"). Style a complete, realistic, age-appropriate outfit AROUND it.
Rules:
- Choose complementary pieces for every OTHER region that suit the product's fabric, color, formality and season. Pieces must be simple, solid or very subtle, no logos/text/bold prints, and must never repeat the product's own pattern.
- Match the product's character and formality instead of defaulting to basics: e.g. a romantic floral or tiered piece suits a soft knit or a fitted blouse with ballet flats, Mary Janes or sandals; a sporty denim piece suits sneakers; a flowy maxi suits sandals or espadrilles. Do NOT default to a white t-shirt with white sneakers unless it is genuinely the best match. Use colors that harmonize with the product's palette, not always white.
- If the product is a bottom, the top must be fully tucked in so the whole waistband stays visible.
- Add at most one subtle accessory (or none). If the product is a top, a set or a one_piece, do NOT use a necklace or anything worn on the chest.
- If the product is a set or a one_piece, the product covers the whole body: only choose footwear, socks/tights and at most one hair accessory.
- Pick exactly {$c['extra_poses']} poses from the list below that best show THIS product. Avoid poses where hands/arms would hide the product and poses that need features the product does not have (e.g. pockets).
Poses:
{$poses}

Answer ONLY with JSON, no markdown:
{"product_summary": "...", "outfit_pieces": {"top": "...", "bottom": "...", "footwear": "...", "socks_or_tights": "...", "accessory": "..."},
 "outfit_sentence": "one English sentence describing the full outfit EXCEPT the product, very concrete (colors, materials, fit)",
 "poses": ["pose_key"], "length": "mini|knee|midi|maxi", "reasoning_tr": "kısa Türkçe gerekçe"}
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
            'top'       => 'a plain light-grey fitted short-sleeve cotton t-shirt, tucked in',
            'set', 'one_piece' => "a plain light-grey fitted short-sleeve cotton t-shirt tucked into {$bottom}",
            default     => $bottom . ', with the top fully tucked into it so the whole waistband is visible',
        };
    }

    public function outfitText(string $outfitSentence, string $productSummary, string $region, string $length): string
    {
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
