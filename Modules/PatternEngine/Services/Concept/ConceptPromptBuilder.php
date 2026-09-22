<?php

namespace Modules\Atelier\Services\Concept;

/**
 * Tarif → text-to-image prompt'u.
 *
 * Çıktı bir GİYSİ TASARIM KONSEPTİdir (düz/flat ürün sunumu), insan/çocuk
 * figürü değil — hem telif/güvenlik hem de kalıp eşlemesi için uygun (yol
 * haritası §3 telif, çocuk içeriği riskini de azaltır).
 */
class ConceptPromptBuilder
{
    public function build(ConceptRequest $request): string
    {
        $lines = [
            'Flat-lay product design concept of a single ' . trim($request->productType) . ' garment.',
            'Studio catalog style, plain neutral background, no human model, no mannequin, no logos, no text.',
            'Centered, soft even lighting, true-to-fabric colors, high detail on print and texture.',
        ];

        if ($this->filled($request->motif)) {
            $lines[] = 'Pattern / motif: ' . trim($request->motif) . '.';
        }
        if ($this->filled($request->palette)) {
            $lines[] = 'Color palette: ' . trim($request->palette) . '.';
        }
        if ($this->filled($request->style)) {
            $lines[] = 'Style: ' . trim($request->style) . '.';
        }
        if ($this->filled($request->description)) {
            $lines[] = 'Additional brief: ' . trim($request->description) . '.';
        }

        return implode("\n", $lines);
    }

    private function filled(?string $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
