<?php

namespace Modules\PatternEngine\DTOs;

/**
 * AI önerisi veya kullanıcı girdisi — Pattern Engine bunu tek girdi olarak görür
 * (mimari doküman §P2, §6.3). `null` alanlar Python tarafında `rules.yaml`'daki
 * varsayılan formülden türetilir.
 *
 * Bu modül hiçbir modüle bağımlı olmadığı için (Marketplace izolasyon deseni,
 * bkz. module.json) DTO kullanımı istisnadır — CLAUDE.md'deki sanctioned
 * istisna listesine eklenmelidir.
 */
final class DesignParamsDTO
{
    /**
     * @param  array<string,string>  $source      alan adı => 'ai' | 'user'
     * @param  array<string,float>   $confidence  alan adı => 0..1 güven skoru
     */
    public function __construct(
        public readonly ?float $neckDepthFront = null,
        public readonly ?float $sleeveLength = null,
        public readonly ?float $bodyLength = null,
        public readonly float $hemFlare = 0.0,
        public readonly string $fit = 'regular',
        public readonly array $source = [],
        public readonly array $confidence = [],
    ) {}

    public function toArray(): array
    {
        return [
            'neck_depth_front' => $this->neckDepthFront,
            'sleeve_length'    => $this->sleeveLength,
            'body_length'      => $this->bodyLength,
            'hem_flare'        => $this->hemFlare,
            'fit'              => $this->fit,
            // Boş PHP dizisi json_encode'da "[]" olur, FastAPI/pydantic dict bekliyor ("{}").
            // Boşken stdClass'a çevirip JSON'da nesne olarak serileşmesini garanti ederiz.
            'source'           => empty($this->source) ? new \stdClass() : $this->source,
            'confidence'       => empty($this->confidence) ? new \stdClass() : $this->confidence,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            neckDepthFront: isset($data['neck_depth_front']) ? (float) $data['neck_depth_front'] : null,
            sleeveLength: isset($data['sleeve_length']) ? (float) $data['sleeve_length'] : null,
            bodyLength: isset($data['body_length']) ? (float) $data['body_length'] : null,
            hemFlare: (float) ($data['hem_flare'] ?? 0.0),
            fit: (string) ($data['fit'] ?? 'regular'),
            source: is_array($data['source'] ?? null) ? $data['source'] : [],
            confidence: is_array($data['confidence'] ?? null) ? $data['confidence'] : [],
        );
    }
}
