<?php

namespace Modules\Atelier\Services\Concept\Drivers;

use Modules\Atelier\Services\Concept\ConceptRequest;
use Modules\Atelier\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Modules\Creative\Services\Ai\Drivers\Mock\MockImageFactory;

/**
 * Gemini anahtarı yokken devreye giren placeholder konsept üreticisi.
 * Anahtarsız dev/test için her varyantı farklı tonda bir PNG olarak üretir.
 */
class MockConceptGenerator implements ConceptImageGeneratorContract
{
    use StoresConcepts;

    /** Varyantları görsel olarak ayırt etmek için döngüsel ton paleti. */
    private const SWATCHES = ['#3b4252', '#a3be8c', '#bf616a', '#ebcb8b'];

    public function __construct(private MockImageFactory $images) {}

    public function generate(ConceptRequest $request): array
    {
        $count = max(1, $request->count);

        $paths = [];
        for ($i = 0; $i < $count; $i++) {
            $color   = self::SWATCHES[$i % count(self::SWATCHES)];
            $label   = 'MOCK • ' . $request->productType . ' #' . ($i + 1);
            $bytes   = $this->images->make(768, 1024, $color, $label);
            $paths[] = $this->store($bytes);
        }

        return $paths;
    }
}
