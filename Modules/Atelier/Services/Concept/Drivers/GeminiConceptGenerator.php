<?php

namespace Modules\Atelier\Services\Concept\Drivers;

use Modules\Atelier\Services\Concept\ConceptPromptBuilder;
use Modules\Atelier\Services\Concept\ConceptRequest;
use Modules\Atelier\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;

/**
 * Gemini ile konsept görseli üreten sürücü.
 *
 * Creative modülünün GeminiClient'ını yeniden kullanır (TEXT+IMAGE modalite
 * kuralı orada çözülmüş). Telif/güvenlik için referans görsel GÖNDERİLMEZ —
 * yalnızca metin prompt'u (yol haritası §3).
 */
class GeminiConceptGenerator implements ConceptImageGeneratorContract
{
    use StoresConcepts;

    public function __construct(
        private GeminiClient $client,
        private ConceptPromptBuilder $prompts,
    ) {}

    public function generate(ConceptRequest $request): array
    {
        $prompt = $this->prompts->build($request);
        $count  = max(1, $request->count);

        $paths = [];
        for ($i = 0; $i < $count; $i++) {
            $bytes   = $this->client->generateImage($prompt);
            $paths[] = $this->store($bytes);
        }

        return $paths;
    }
}
