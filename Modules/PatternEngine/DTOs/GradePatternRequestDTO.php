<?php

namespace Modules\PatternEngine\DTOs;

/**
 * `PatternEngineClient::gradeGenerate()` girdisi (§4.5 — Sprint 3).
 *
 * Taban beden + hedef bedenlerin listesi; sabit bir katsayı YOK — her beden
 * Python tarafında kendi taban ölçülerinden bağımsız çözülür.
 */
final class GradePatternRequestDTO
{
    /** @param  array<int,string>  $targetSizes */
    public function __construct(
        public readonly string $garment,
        public readonly string $baseSize,
        public readonly array $targetSizes,
        public readonly string $profile,
        public readonly DesignParamsDTO $designParams,
    ) {}

    public function toArray(): array
    {
        return [
            'garment'       => $this->garment,
            'base_size'     => $this->baseSize,
            'target_sizes'  => array_values($this->targetSizes),
            'profile'       => $this->profile,
            'design_params' => $this->designParams->toArray(),
        ];
    }
}
