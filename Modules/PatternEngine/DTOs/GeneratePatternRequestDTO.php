<?php

namespace Modules\PatternEngine\DTOs;

/**
 * `PatternEngineClient::generate()` girdisi. Çağıran taraf (ör. Product/Atelier)
 * kendi Eloquent verisinden bu DTO'yu kurar — PatternEngine hiçbir modülün
 * modeline bağımlı olmaz.
 */
final class GeneratePatternRequestDTO
{
    public function __construct(
        public readonly string $garment,
        public readonly string $size,
        public readonly string $profile,
        public readonly DesignParamsDTO $designParams,
    ) {}

    public function toArray(): array
    {
        return [
            'garment'       => $this->garment,
            'size'          => $this->size,
            'profile'       => $this->profile,
            'design_params' => $this->designParams->toArray(),
        ];
    }
}
