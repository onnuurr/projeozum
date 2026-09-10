<?php

namespace Modules\PatternEngine\DTOs;

/**
 * `PatternEngineClient::generate()` çıktısı (mikroservisten dönen yapı).
 *
 * `dxfPath` Laravel tarafının storage'ına yazılmış DXF dosyasının yoludur —
 * servis DXF'i base64 döner, Laravel kendi diskine yazar (iki taraf dosya
 * sistemini paylaşmaz, ağ sınırı net kalır).
 */
final class PatternResultDTO
{
    /**
     * @param  array<int,PieceDTO>              $pieces
     * @param  array<int,ValidationFindingDTO>  $findings
     * @param  array<int,string>                $warnings
     */
    public function __construct(
        public readonly string $garment,
        public readonly string $size,
        public readonly string $profile,
        public readonly array $pieces,
        public readonly string $dxfPath,
        public readonly array $findings,
        public readonly bool $allPassed,
        public readonly array $warnings = [],
    ) {}

    public function failures(): array
    {
        return array_values(array_filter($this->findings, static fn (ValidationFindingDTO $f) => ! $f->passed));
    }
}
