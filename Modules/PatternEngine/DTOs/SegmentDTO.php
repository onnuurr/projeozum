<?php

namespace Modules\PatternEngine\DTOs;

/** mimari doküman §6.2 `Segment` — iki nokta arasındaki kenar (line|arc|spline). */
final class SegmentDTO
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
        public readonly string $kind,   // line | arc | spline
        public readonly string $role,   // cut | seam | internal | fold
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            start: (string) $data['start'],
            end: (string) $data['end'],
            kind: (string) $data['kind'],
            role: (string) $data['role'],
        );
    }
}
