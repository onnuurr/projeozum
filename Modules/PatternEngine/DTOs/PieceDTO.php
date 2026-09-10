<?php

namespace Modules\PatternEngine\DTOs;

/** mimari doküman §6.2 `Piece` — bir kalıp parçası (ör. front_bodice). */
final class PieceDTO
{
    /**
     * @param  array<string,NamedPointDTO>  $points
     * @param  array<int,SegmentDTO>        $segments
     * @param  array{0:string,1:string}     $grainline
     */
    public function __construct(
        public readonly string $name,
        public readonly bool $mirrored,
        public readonly float $seamAllowance,
        public readonly array $grainline,
        public readonly array $points,
        public readonly array $segments,
    ) {}

    public static function fromArray(array $data): self
    {
        $points = [];
        foreach ($data['points'] ?? [] as $id => $point) {
            $points[$id] = NamedPointDTO::fromArray((string) $id, $point);
        }

        $segments = array_map(
            static fn (array $segment) => SegmentDTO::fromArray($segment),
            $data['segments'] ?? [],
        );

        return new self(
            name: (string) $data['name'],
            mirrored: (bool) ($data['mirrored'] ?? false),
            seamAllowance: (float) ($data['seam_allowance'] ?? 10.0),
            grainline: [(string) ($data['grainline'][0] ?? ''), (string) ($data['grainline'][1] ?? '')],
            points: $points,
            segments: $segments,
        );
    }
}
