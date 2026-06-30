<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

/**
 * Gemini try-on (giydirme) prompt'u üretir.
 *
 * Kritik: BİRİNCİ görseldeki kişinin kimliği, duruşu, çerçevesi ve arka planı
 * birebir korunur; yalnızca üzerindeki taban kıyafet, İKİNCİ görseldeki ürünle
 * (giysiyle) değiştirilir. Ürünün gerçek kesim/renk/kumaş/desen/marka/yazı
 * detayları aynen taşınır — yeniden tasarlanmaz, başka ürün uydurulmaz.
 */
class GeminiTryOnPromptBuilder
{
    public function build(): string
    {
        $lines = [
            'You are given two images. The FIRST image is a PERSON (a model in a specific pose).',
            'The SECOND image is a GARMENT/PRODUCT (a piece of clothing or wearable item).',
            'Task: dress the person from the first image in the garment from the second image — a virtual try-on.',
            'Keep the person strictly consistent with the first image: same face, hairstyle, skin tone, body type, exact pose, body orientation, framing, lighting and background. Do not change the person or the scene.',
            'Replace the person\'s current base clothing with the product from the second image so it is naturally worn on the correct body region (top, bottom, dress, outerwear, etc.).',
            'Preserve the product\'s real cut, silhouette, colors, fabric, texture, patterns, prints, logos, branding and any text EXACTLY as in the second image — do not redesign, recolor, restyle or invent a different product.',
            'Make the garment fit, drape and fold realistically on the body with correct shadows and contact, matching the first image\'s lighting.',
            'Single person, full body visible from head to feet, centered, photorealistic, sharp focus, correct anatomy, hands and proportions, no text or watermark added.',
        ];

        return implode(' ', $lines);
    }
}
