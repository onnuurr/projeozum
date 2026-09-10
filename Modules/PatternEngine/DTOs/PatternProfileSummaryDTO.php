<?php

namespace Modules\PatternEngine\DTOs;

/** `GET /profiles` listesindeki tek satır — çok profillilik özeti (§5.2, Sprint 4). */
final class PatternProfileSummaryDTO
{
    /** @param  array<int,string>  $sizes */
    public function __construct(
        public readonly string $name,
        public readonly string $garment,
        public readonly array $sizes,
        public readonly string $source,
        public readonly bool $verifiedByModelist,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            garment: (string) $data['garment'],
            sizes: array_values(array_map('strval', (array) ($data['sizes'] ?? []))),
            source: (string) ($data['source'] ?? ''),
            verifiedByModelist: (bool) ($data['verified_by_modelist'] ?? false),
        );
    }
}
