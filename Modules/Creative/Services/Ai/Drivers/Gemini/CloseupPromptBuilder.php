<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\CloseupRequest;

/**
 * Yakın plan / detay çekimi prompt'u.
 *
 * Kritik: bu bir "aynı çekimden kameranın yaklaştığı kare"dir — sahne, ışık, manken ve
 * kombin BİRİNCİ görselden gelir; ürünün desen/doku/dikiş/donanım ayrıntıları ise
 * İKİNCİ (ve sonraki) görsellerden birebir alınır. Yakın kadrajda modelin en çok
 * uydurduğu şey dokudur; bu yüzden ürün fotoğrafı her zaman sadakat referansıdır.
 */
class CloseupPromptBuilder
{
    public function build(CloseupRequest $request): string
    {
        $lines = [
            'You are given two or more images. The FIRST image is a finished e-commerce catalog photo of a person wearing a PRODUCT (plus complementary styling pieces).',
            'The SECOND image is the original photo of that PRODUCT on its own.',
            sprintf('Task: produce a new photo from the SAME photo shoot — the camera simply moves closer for %s.', rtrim($request->shotPrompt, '.')),
            'Keep everything consistent with the first image: the same person, skin tone, the same outfit and styling pieces, the same pose (as far as it is visible in this framing), the same studio background, lighting direction and color temperature. Do not restyle, re-dress or re-pose the person.',
            'The product must look EXACTLY like the product photo at this closer distance: identical colors, fabric texture and weave/knit, print or pattern (at its true scale and placement), seams, stitching, trims, hardware, labels, logos and any text — never invent, simplify, redesign, recolor or add details that are not in the product photo.',
            'The garment\'s color, hue and saturation must render as colorimetrically identical to its reference photo — no color grading, white balance shift or tint on the garment fabric.',
        ];

        if ($request->extraGarmentImages !== []) {
            $labels = [];
            foreach (array_values($request->extraGarmentImages) as $i => $extra) {
                $labels[] = sprintf('image #%d%s', $i + 3, ($extra['label'] ?? null) ? ' ("' . $extra['label'] . '" view)' : '');
            }
            $lines[] = 'Additional reference images of the SAME product are provided (' . implode(', ', $labels) . ') — use them as the ground truth for close-up details (stitching, hardware, texture, back/side construction) that the main product photo does not show clearly; they are not separate garments.';
        }

        $lines[] = 'Realistic macro/close-up product photography: sharp focus on the product, natural shallow depth of field, true-to-life fabric micro-texture, natural folds and contact shadows where the fabric meets the body.';
        $lines[] = PromptDirectives::realism();
        $lines[] = 'Correct anatomy for any visible body parts (hands, fingers, neck, legs). Single person only, no added props, no text or watermark.';

        if ($request->extraInstruction !== null && trim($request->extraInstruction) !== '') {
            $lines[] = 'IMPORTANT correction: the previous attempt was rejected for this exact reason, fix it in this generation: ' . trim($request->extraInstruction);
        }

        return implode(' ', $lines);
    }
}
