<?php

namespace Modules\PatternEngine\DTOs;

/**
 * `PatternEngineClient::saveProfile()` girdisi — yeni bir profil yaratır ya da
 * var olanı günceller (§5.2, Sprint 4). Yalnızca ölçü tablosu (`sizes`) buradan
 * gelir; çizim formülleri (`rules.yaml`) `baseProfile`den kopyalanır.
 */
final class SaveProfileRequestDTO
{
    /** @param  array<string,array<string,float>>  $sizes */
    public function __construct(
        public readonly string $name,
        public readonly array $sizes,
        public readonly ProvenanceDTO $provenance,
        public readonly string $baseProfile = 'ts_default',
    ) {}

    public function toArray(): array
    {
        return [
            'sizes'        => $this->sizes,
            'provenance'   => $this->provenance->toArray(),
            'base_profile' => $this->baseProfile,
        ];
    }
}
