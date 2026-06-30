<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\PoseRequest;

/**
 * PoseRequest'ten Gemini poz önizleme prompt'u üretir.
 *
 * Kimlikten bağımsız: nötr, jenerik bir manken figürü verilen duruşta gösterilir.
 * Amaç operatörün pozu görerek seçebilmesi; gerçek kimlik try-on'da uygulanır.
 */
class PosePromptBuilder
{
    public function build(PoseRequest $request): string
    {
        if ($request->promptOverride) {
            return $request->promptOverride;
        }

        $lines = [
            'Generate a clean, identity-neutral full-body reference image of a generic, featureless light-gray mannequin figure (no specific face, no branding).',
            sprintf('Show the mannequin in this pose: %s.', $request->directive),
            'Full-body framing, the entire figure visible from head to feet, single figure centered.',
            'Plain seamless white studio background, soft even lighting, no props, no text or watermark.',
            'Clear silhouette so the pose is easy to read.',
        ];

        return implode(' ', $lines);
    }
}
