<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Support\CreativeSpec;
use Modules\Creative\Services\Ai\SceneRequest;

/**
 * SceneRequest'ten Gemini görsel kurgu prompt'u üretir.
 * Brand palette varsa ton/renk ipucu olarak prompt'a katılır.
 */
class GeminiPromptBuilder
{
    /**
     * Poz planlama kataloğu: override verilmediğinde kullanılacak,
     * ürünü öne çıkaran kürate edilmiş manken duruşları.
     *
     * @var array<int,string>
     */
    private const POSE_PRESETS = [
        'a relaxed three-quarter standing pose, weight on one leg and shoulders angled slightly to camera',
        'a confident frontal stance with chin level and arms resting naturally so the product stays fully visible',
        'a dynamic walking pose mid-stride that conveys movement while keeping the product in sharp focus',
        'a seated editorial pose with an elongated silhouette and the product clearly presented to camera',
    ];

    public function build(SceneRequest $request): string
    {
        if ($request->promptOverride) {
            return $request->promptOverride;
        }

        $lines = [
            'Use the provided product photo(s) as the EXACT subject of the image.',
            sprintf('Create a photorealistic, social-media-ready scene that prominently and faithfully features this product ("%s").', $request->productName),
            'Critically: preserve the product\'s real shape, colors, materials, branding, text and details exactly as in the reference photos — do not redesign, replace or invent a different product.',
            'Place it naturally in an attractive, clean lifestyle or studio setting with tasteful props and soft realistic lighting.',
        ];

        // Creative Rules Engine spec'i varsa kanal/format'a özel kompozisyon
        // kuralları eklenir (2026-09-22, bkz. CreativeSpec docblock'u).
        if ($request->spec) {
            $lines = array_merge($lines, $this->specLines($request->spec));
        } else {
            $lines[] = 'Leave some uncluttered space for overlaid text.';
        }

        // Manken + poz planlaması: ürünü taşıyacak modeli ve duruşunu tarif eder.
        $lines[] = $this->posePlan($request);

        if ($request->aspectLabel) {
            $lines[] = sprintf('Frame the composition for a %s aspect ratio social media image.', $request->aspectLabel);
        } else {
            $lines[] = 'Composition suitable for a vertical social media post.';
        }

        if ($palette = $this->paletteHint($request->palette)) {
            $lines[] = $palette;
        }

        if ($brief = $this->designBriefHint($request->designBrief)) {
            $lines[] = $brief;
        }

        return implode(' ', $lines);
    }

    /**
     * CreativeSpec'ten kanal/format'a özel yönergeler üretir. KRİTİK: hiçbir
     * satır AI'dan metin/logo/fiyat/CTA ÇİZMESİNİ istemez — bunlar her zaman
     * spec'ten bağımsız, ayrı bir render adımında (SVG şablon motoru)
     * yerleştirilir. Bu fonksiyon yalnızca atmosfer/kompozisyon/dekoratif
     * öğe sınırlarını tarif eder.
     *
     * @return array<int,string>
     */
    private function specLines(CreativeSpec $spec): array
    {
        $lines = [
            sprintf('Visual style: %s.', str_replace('_', ' ', $spec->visualStyle)),
            'Do NOT render any text, numbers, prices, logos, phone numbers, website addresses, '
            . 'or calls-to-action anywhere in the image — leave clean, uncluttered negative space '
            . 'where such elements will be added separately in a precise post-processing step.',
        ];

        if ($spec->logoRequired) {
            $lines[] = $spec->logoSafeArea
                ? 'Reserve a clean, empty corner or edge area suitable for a logo overlay — do not place busy detail there.'
                : 'Leave room for a logo overlay.';
        }

        if ($spec->partnerLogoRequired) {
            $lines[] = 'Reserve a second clean area (distinct from the brand logo area) for a partner logo overlay.';
        }

        $lines[] = $spec->maxDecorativeElements > 0
            ? sprintf('You may include up to %d tasteful decorative visual elements (props, shapes, light effects) — no more.', $spec->maxDecorativeElements)
            : 'Keep the scene free of extra decorative props — focus entirely on the product and atmosphere.';

        if ($spec->bannedWords !== []) {
            // Metin zaten yasak (yukarıda) ama marka banned_words'ü varsa AI'ın
            // arka plandaki tabela/ambalaj gibi öğelerde bile bu kelimeleri
            // "hayal etmemesi" için ayrıca belirtiyoruz.
            $lines[] = 'Do not depict or imply any of these words/brands in the scene: '
                . implode(', ', $spec->bannedWords) . '.';
        }

        return $lines;
    }

    /**
     * Manken ve poz planlama yönergesini üretir. Override yoksa ürüne göre
     * kürate bir poz seçilir; ürünün görünürlüğü ve doğru anatomi vurgulanır.
     */
    private function posePlan(SceneRequest $request): string
    {
        $pose = $request->pose !== null && trim($request->pose) !== ''
            ? trim($request->pose)
            : $this->defaultPose($request);

        return sprintf(
            'Present the product on a realistic human model posed in %s. '
            . 'Plan the pose so the product stays fully visible, unobstructed and naturally worn or held, '
            . 'keeping anatomy, hands and proportions correct with an intentional gaze.',
            $pose,
        );
    }

    /**
     * Override yokken ürün adına göre deterministik bir preset seçer;
     * böylece aynı ürünün tekrar render'larında poz kararlı kalır.
     */
    private function defaultPose(SceneRequest $request): string
    {
        $index = abs(crc32($request->productName)) % count(self::POSE_PRESETS);

        return self::POSE_PRESETS[$index];
    }

    /**
     * @param  array<string,string>  $palette
     */
    private function paletteHint(array $palette): ?string
    {
        $colors = array_filter([
            $palette['primary']   ?? null,
            $palette['accent']    ?? null,
            $palette['secondary'] ?? null,
        ]);

        if ($colors === []) {
            return null;
        }

        return 'Match the brand color mood: ' . implode(', ', $colors) . '.';
    }

    private function designBriefHint(?string $brief): ?string
    {
        $brief = trim((string) $brief);

        return $brief !== '' ? 'Follow this brand style guide for the overall mood/scene: ' . $brief : null;
    }
}
