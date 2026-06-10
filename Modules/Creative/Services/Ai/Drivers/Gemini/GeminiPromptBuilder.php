<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\SceneRequest;

/**
 * SceneRequest'ten Gemini görsel kurgu prompt'u üretir.
 * Brand palette varsa ton/renk ipucu olarak prompt'a katılır.
 */
class GeminiPromptBuilder
{
    public function build(SceneRequest $request): string
    {
        if ($request->promptOverride) {
            return $request->promptOverride;
        }

        $lines = [
            'Generate a photorealistic lifestyle scene of a human fashion model suitable for a social media product post.',
            sprintf('The scene should complement the product "%s".', $request->productName),
            'The model should have a natural pose, clean studio or lifestyle background, soft realistic lighting.',
            'Leave the torso/garment area clearly visible and unobstructed for a later virtual try-on step.',
        ];

        if ($palette = $this->paletteHint($request->palette)) {
            $lines[] = $palette;
        }

        return implode(' ', $lines);
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
}
