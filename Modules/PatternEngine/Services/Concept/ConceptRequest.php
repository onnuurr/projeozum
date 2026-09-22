<?php

namespace Modules\Atelier\Services\Concept;

use Modules\Atelier\Models\DesignCard;

/**
 * AI konsept üretimi için tarif (değer nesnesi).
 *
 * AI çıktısı yalnızca piksel görseldir (desen/renk/stil fikri); ölçü/geometri
 * içermez. Geometri kütüphanedeki DXF'ten gelir (yol haritası §9.1).
 */
class ConceptRequest
{
    /**
     * @param  string       $productType  Ürün tipi (tulum/ceket/kapüşonlu...) — kalıp eşlemesinin birincil alanı.
     * @param  string|null  $description  Serbest tarif metni (kullanıcının kendi cümlesi).
     * @param  string|null  $palette      Renk yönü (örn. "lacivert + krem").
     * @param  string|null  $motif        Desen/motif (örn. "küçük çiçekler").
     * @param  string|null  $style        Stil notu (örn. "minimal", "retro").
     * @param  string|null  $sizeRange    Hedef beden aralığı (metin).
     * @param  int          $count        Üretilecek varyant sayısı.
     * @param  string       $source       Akış X (pattern_first) / Akış Y (concept_first).
     * @param  int|null     $patternId    Akış X'te baştan seçili kalıp.
     */
    public function __construct(
        public string $productType,
        public ?string $description = null,
        public ?string $palette = null,
        public ?string $motif = null,
        public ?string $style = null,
        public ?string $sizeRange = null,
        public int $count = 2,
        public string $source = DesignCard::SOURCE_CONCEPT_FIRST,
        public ?int $patternId = null,
    ) {}

    /** Kuyruk işinin tarifi yeniden kurabilmesi için serileştirir. */
    public function toArray(): array
    {
        return [
            'productType' => $this->productType,
            'description' => $this->description,
            'palette'     => $this->palette,
            'motif'       => $this->motif,
            'style'       => $this->style,
            'sizeRange'   => $this->sizeRange,
            'count'       => $this->count,
            'source'      => $this->source,
            'patternId'   => $this->patternId,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productType: (string) ($data['productType'] ?? 'belirsiz'),
            description: $data['description'] ?? null,
            palette: $data['palette'] ?? null,
            motif: $data['motif'] ?? null,
            style: $data['style'] ?? null,
            sizeRange: $data['sizeRange'] ?? null,
            count: (int) ($data['count'] ?? 2),
            source: (string) ($data['source'] ?? DesignCard::SOURCE_CONCEPT_FIRST),
            patternId: isset($data['patternId']) ? (int) $data['patternId'] : null,
        );
    }
}
