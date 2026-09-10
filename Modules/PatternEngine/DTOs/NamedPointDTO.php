<?php

namespace Modules\PatternEngine\DTOs;

/** mimari doküman §6.2 `NamedPoint` — mm cinsinden isimli nokta. */
final class NamedPointDTO
{
    public function __construct(
        public readonly string $id,
        public readonly float $x,
        public readonly float $y,
    ) {}

    public static function fromArray(string $id, array $data): self
    {
        return new self(id: $id, x: (float) $data['x'], y: (float) $data['y']);
    }
}
