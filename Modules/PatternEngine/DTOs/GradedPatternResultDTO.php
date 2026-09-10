<?php

namespace Modules\PatternEngine\DTOs;

/**
 * `PatternEngineClient::gradeGenerate()` çıktısı — her beden bağımsız çözülüp
 * ayrı ayrı doğrulanır (§4.5); tek bir DXF'te her beden kendi satırındadır.
 */
final class GradedPatternResultDTO
{
    /**
     * @param  array<int,string>                        $sizes
     * @param  array<string,array<int,PieceDTO>>         $piecesBySize
     * @param  array<string,array<int,ValidationFindingDTO>>  $findingsBySize
     * @param  array<string,bool>                        $allPassedBySize
     * @param  array<int,string>                         $warnings
     */
    public function __construct(
        public readonly string $garment,
        public readonly string $profile,
        public readonly array $sizes,
        public readonly array $piecesBySize,
        public readonly string $dxfPath,
        public readonly array $findingsBySize,
        public readonly array $allPassedBySize,
        public readonly array $warnings = [],
    ) {}

    public function allPassed(): bool
    {
        return ! in_array(false, $this->allPassedBySize, true);
    }
}
