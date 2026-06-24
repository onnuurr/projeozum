<?php

namespace Modules\Atelier\Services\Conversion;

use Modules\Atelier\Models\ConversionJob;

/**
 * PDF→DXF dönüştürme çıktısı (mikroservisten dönen yapı).
 *
 * Sınıflandırma yalnızca triyaj rengidir; onay her zaman insandadır (§2.4).
 */
class ConversionResult
{
    /**
     * @param  string             $classification  green | yellow | red
     * @param  float              $confidence      0–100 güven skoru
     * @param  string|null        $dxf             üretilen DXF ham içeriği (yoksa null)
     * @param  array<string,mixed> $metadata       product_type, size_range, parts[], grid, scale_*
     * @param  array<int,string>  $errors          insan onayına düşüren uyarı/hatalar
     */
    public function __construct(
        public string $classification,
        public float $confidence,
        public ?string $dxf = null,
        public array $metadata = [],
        public array $errors = [],
    ) {}

    public static function fromResponse(array $json): self
    {
        $class = (string) ($json['classification'] ?? ConversionJob::CLASS_RED);
        $dxf   = null;
        if (! empty($json['dxf_base64']) && is_string($json['dxf_base64'])) {
            $decoded = base64_decode($json['dxf_base64'], true);
            $dxf = $decoded === false ? null : $decoded;
        }

        return new self(
            classification: in_array($class, [ConversionJob::CLASS_GREEN, ConversionJob::CLASS_YELLOW, ConversionJob::CLASS_RED], true)
                ? $class : ConversionJob::CLASS_RED,
            confidence: (float) ($json['confidence'] ?? 0),
            dxf: $dxf,
            metadata: is_array($json['metadata'] ?? null) ? $json['metadata'] : [],
            errors: array_values(array_filter((array) ($json['errors'] ?? []), 'is_string')),
        );
    }
}
