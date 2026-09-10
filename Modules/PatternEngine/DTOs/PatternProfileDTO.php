<?php

namespace Modules\PatternEngine\DTOs;

/** `GET/PUT /profiles/{name}` çıktısı — bir profilin tam ölçü tablosu (§5.2, §5.3). */
final class PatternProfileDTO
{
    /** @param  array<string,array<string,float>>  $sizes  beden anahtarı => ölçü alanları */
    public function __construct(
        public readonly string $name,
        public readonly string $garment,
        public readonly array $sizes,
        public readonly ProvenanceDTO $provenance,
    ) {}

    public static function fromArray(array $data): self
    {
        $sizes = [];
        foreach ((array) ($data['sizes'] ?? []) as $sizeKey => $measurements) {
            $sizes[(string) $sizeKey] = array_map('floatval', (array) $measurements);
        }

        return new self(
            name: (string) $data['name'],
            garment: (string) ($data['garment'] ?? ''),
            sizes: $sizes,
            provenance: ProvenanceDTO::fromArray((array) ($data['provenance'] ?? [])),
        );
    }
}
