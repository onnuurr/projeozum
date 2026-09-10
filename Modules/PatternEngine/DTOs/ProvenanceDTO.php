<?php

namespace Modules\PatternEngine\DTOs;

/**
 * Bir profilin ölçü kaynağı ve onay durumu (mimari doküman §5.3).
 *
 * `verifiedByModelist=false` iken üretim vaadi verilmez — bkz. PatternEngineJobs.vue
 * uyarı banner'ı ve `sizes.yaml`'daki `provenance` alanı.
 */
final class ProvenanceDTO
{
    public function __construct(
        public readonly string $source,
        public readonly bool $verifiedByModelist = false,
        public readonly ?string $verifiedAt = null,
        public readonly ?string $note = null,
    ) {}

    public function toArray(): array
    {
        return [
            'source'               => $this->source,
            'verified_by_modelist' => $this->verifiedByModelist,
            'verified_at'          => $this->verifiedAt,
            'note'                 => $this->note,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            source: (string) ($data['source'] ?? ''),
            verifiedByModelist: (bool) ($data['verified_by_modelist'] ?? false),
            verifiedAt: isset($data['verified_at']) ? (string) $data['verified_at'] : null,
            note: isset($data['note']) ? (string) $data['note'] : null,
        );
    }
}
