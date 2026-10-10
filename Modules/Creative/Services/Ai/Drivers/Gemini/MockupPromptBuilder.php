<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

/**
 * Mokap giydirme prompt'ları (stilist, ana kare, türetilen poz, giydirme).
 * Metinler 2026-10-05 pilotunda (3 deneme, 5 ürün tipi) doğrulanan hâlleridir.
 * Prompt değişince STYLIST_VERSION elle artırılır (sonuç satırlarına yazılır).
 */
class MockupPromptBuilder
{
    public const STYLIST_VERSION = 4;   // 2: seçenek listesi + kural motoru (Faz 3); 3: ürünün birden çok fotoğrafı; 4: poz seçimi stilistten çıktı (rastgele)

    /** Baz (ana kare + türetilen poz) prompt sürümü; baz satırının masks.prompt_version alanına yazılır. */
    public const BASE_VERSION = 3;      // 2: canlı çekim — doğal beden dili, şablon pozu birebir değil; 3: kare başına farklı yüz ifadesi (2026-10-10)

    /**
     * 2026-10-10: bazlar "nötr manken şablonunu BİREBİR kopyala" + ifade talimatı yokken kaskatı, ifadesiz
     * (vitrin mankeni gibi) çıkıyordu. Gerçek bir katalog çekimi: rahat, doğal duruş; ifade EXPRESSIONS'tan.
     */
    private const LIVELY = 'This is a real catalog photo shoot of a real, lively model — NOT a stiff store mannequin. '
        . 'Natural, relaxed body language: a slight weight shift, soft knees, relaxed shoulders, relaxed fingers and natural, slightly asymmetric arms and hands — '
        . 'never rigid, symmetrical, robotic or posed like a doll.';

    /**
     * Kare başına yüz ifadesi: her karede aynı gülümseme yapay duruyordu (2026-10-10). Ana kare (ürünün kapak
     * görseli) her zaman MASTER_EXPRESSION; türetilen pozlar havuzdan, kombin içinde tekrarsız (MockupBaseService).
     */
    public const EXPRESSIONS = [
        'soft_smile'  => 'a soft, natural closed-mouth smile, relaxed eyes looking into the camera',
        'laugh'       => 'a candid, genuine laugh caught mid-moment — open mouth, eyes crinkling, as if reacting to something funny just off-camera',
        'calm'        => 'a calm, relaxed face with no posed smile — just a faint natural softness around the mouth, quietly confident, eyes to camera',
        'glance_away' => 'a candid in-between moment, glancing off-camera to the side with a small natural smile, not looking into the lens',
        'playful'     => 'a playful, slightly cheeky lopsided grin with bright eyes',
        'thoughtful'  => 'a gentle, thoughtful look slightly down and aside with only a hint of a smile',
    ];

    public const MASTER_EXPRESSION = 'soft_smile';

    private function expression(?string $key): string
    {
        $text = self::EXPRESSIONS[$key] ?? self::EXPRESSIONS[self::MASTER_EXPRESSION];

        return "Facial expression in THIS photo: {$text}. It must look spontaneous and real — not a frozen, forced or stock catalog smile.";
    }

    private const POSE_TEMPLATE = 'Follow its overall body pose closely (stance, which leg carries the weight, arm and hand positions, head and body direction) '
        . 'but hold it the way a real model naturally would in a photo shoot — loose and relaxed, not a rigid copy; ignore its appearance and its blank expression.';

    private const STUDIO = 'Plain seamless light-grey studio background with soft, even, diffused frontal studio lighting and a soft natural floor shadow; no props, no window light patterns, no text or watermark.';

    private const FRAMING = 'Full-body framing, the entire body visible from head to feet with some space above the head and below the feet, single person centered.';

    /** Stilistin işaretleyebileceği ürün özellikleri (poz gereksinimleri bunlarla karşılanır). */
    public const PRODUCT_FEATURES = ['pockets', 'collar', 'buttons', 'zipper', 'belt_loops', 'ruffles', 'print'];

    /**
     * Faz 3: stilist serbest kombin YAZMAZ; her tür için listeden sıralı 3 anahtar seçer. Kombin cümlesini
     * MockupStylistRuleEngine seçeneklerin prompt_en alanlarından kurar.
     *
     * Ek pozları stilist seçmez (v4): MockupStylistRuleEngine uygun pozlardan rastgele seçer.
     *
     * @param array{region:string,length:string,age:?string,gender:?string,options:array<string,array<string,string>>} $c
     *        options: kind => key => kısa İngilizce tanım (yalnız bu ürüne uygun olanlar)
     */
    public function stylist(array $c): string
    {
        $options = collect($c['options'])->map(fn ($opts, $kind) => strtoupper($kind) . ":\n" . collect($opts)->map(fn ($d, $k) => "- {$k}: {$d}")->implode("\n"))->implode("\n");
        $kinds = implode(', ', array_map(fn ($k) => "\"{$k}\": [\"key1\",\"key2\",\"key3\"]", array_keys($c['options'])));
        $who = trim(($c['gender'] ? "a {$c['gender']}" : 'a child') . ($c['age'] ? " ({$c['age']})" : ''));
        $features = implode('|', self::PRODUCT_FEATURES);
        $photos = ($c['extra_images'] ?? 0) > 0
            ? 'The attached images are all photos of the SAME PRODUCT we sell (the top and bottom of a set may be photographed separately — judge the product from ALL of them).'
            : 'The attached image is the PRODUCT we sell.';

        return <<<TXT
You are a senior fashion stylist preparing an e-commerce catalog shoot. The model is {$who}.
{$photos} It covers the "{$c['region']}" body region (bottom | top | one_piece | set = matching two-piece) and its length is "{$c['length']}" (if "unknown": judge it from the garment's own proportions — mini | knee | midi | maxi; ankle-length is maxi — and return it as "length").
Style a realistic, age-appropriate outfit AROUND the product by choosing ONLY from the option lists below. For every list, return your 3 best keys in order of preference.
- Match the product's character, formality, colors and season (e.g. a romantic tiered piece → a soft knit or blouse with ballet flats or Mary Janes; a sporty denim piece → sneakers; a flowy maxi → sandals or espadrilles). Do not default to white basics unless they are genuinely the best match.
- COLOR is the color of the complementary garment: it must harmonize with the product's palette and must not copy the product's own color.
- Prefer "none" for the accessory unless one clearly improves the look.
Options:
{$options}

List the product's visible construction features from: {$features}.

Answer ONLY with JSON, no markdown:
{"product_summary": "short English description of the product", "picks": { {$kinds} },
 "features": ["..."], "length": "mini|knee|midi|maxi", "confidence": 0.0-1.0, "reasoning_tr": "kısa Türkçe gerekçe"}
TXT;
    }

    /** Köşe detayı (MockupDetailInset): ürünün en ayırt edici detayı hangi fotoğrafta, nerede. */
    public function detailBox(int $photoCount): string
    {
        $which = $photoCount > 1
            ? "You are given {$photoCount} photos of the SAME product (numbered 1..{$photoCount} in order). Choose the photo that shows the detail most clearly and at the highest effective resolution (usually the closest shot)."
            : 'You are given a photo of a product.';

        return $which . ' Find the single most distinctive, attractive construction detail a shopper would want to see up close '
            . '(e.g. a bow, embroidery or eyelet work, ruffles, a collar, buttons, a print motif, lace, a pocket). '
            . 'Return a tight box around that detail with a little context; it will be shown magnified in a round inset, so avoid boxes that are mostly background or a large plain area. '
            . 'Answer ONLY JSON: {"photo": <1-based photo number>, "box_2d": [ymin, xmin, ymax, xmax] (0-1000), "label_tr": "kısa Türkçe ad, ör. fiyonk"}';
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

    public function masterBase(string $outfitText, ?string $expression = null): string
    {
        return implode(' ', [
            'You are given reference images. The FIRST image is the PERSON (identity source). Keep the person\'s identity strictly consistent: same face, bone structure, eyes, hairstyle, skin tone and body type.',
            'The SECOND image is a POSE TEMPLATE (a neutral mannequin). ' . self::POSE_TEMPLATE,
            $outfitText, self::LIVELY, $this->expression($expression ?? self::MASTER_EXPRESSION),
            'The person must wear shoes (never barefoot). Nothing else — no other accessories.',
            self::FRAMING, self::STUDIO, PromptDirectives::realism(), PromptDirectives::camera(),
        ]);
    }

    public function derivedBase(string $outfitText, ?string $expression = null): string
    {
        return implode(' ', [
            'You are given reference images. The FIRST image is the MASTER photo from this photo shoot: keep the SAME person (face, hairstyle, skin tone, body), the SAME outfit and accessories EXACTLY (every garment, accessory, socks/tights and shoes — same colors, fit and details; never barefoot), the SAME studio background and the SAME lighting.',
            'The SECOND image is a POSE TEMPLATE (a neutral mannequin). Change ONLY the pose and the facial expression: this is a different moment of the same shoot, so do NOT copy the master photo\'s expression. ' . self::POSE_TEMPLATE,
            $outfitText, self::LIVELY, $this->expression($expression),
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
            'set' => "The SECOND image is a product photo of a TWO-PIECE SET (a top and a bottom — TWO separate garments)." . ($productImageCount > 1 ? ' Further images are more photos of the SAME set: the top and the bottom may be photographed together or SEPARATELY — take each piece (its color, length and details) from the photo that shows it.' : '') . " Task: replace BOTH of the person's plain light-grey placeholder garments with the set: the set's top on the upper body, the set's bottom on the legs, worn as two separate pieces." . ($long ? ' The long bottom falls naturally to its true length over/around the shoes, which stay the same shoes.' : ''),
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
