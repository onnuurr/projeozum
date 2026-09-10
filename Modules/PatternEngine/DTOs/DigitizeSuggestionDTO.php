<?php

namespace Modules\PatternEngine\DTOs;

/**
 * `PatternEngineClient::digitize()` çıktısı (§4.1, Sprint 5).
 *
 * `designParams.source`/`confidence` alanları her zaman `'ai'` + bir güven
 * skoru taşır (§P4) — ham sayı değildir. Bu bir ÖNERİDİR; kullanıcı
 * onaylamadan/düzeltmeden `PatternEngineService::queue()`'ya geçilmemelidir
 * (§P2 — AI omurga değil sağlayıcıdır).
 */
final class DigitizeSuggestionDTO
{
    /**
     * @param  array<string,mixed>  $silhouette  piksel oranları (mm YOK, §P1)
     * @param  array<int,string>    $warnings
     */
    public function __construct(
        public readonly DesignParamsDTO $designParams,
        public readonly array $silhouette,
        public readonly array $warnings = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            designParams: DesignParamsDTO::fromArray((array) ($data['design_params'] ?? [])),
            silhouette: (array) ($data['silhouette'] ?? []),
            warnings: array_values(array_filter((array) ($data['warnings'] ?? []), 'is_string')),
        );
    }
}
