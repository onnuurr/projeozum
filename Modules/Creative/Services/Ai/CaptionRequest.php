<?php

namespace Modules\Creative\Services\Ai;

/**
 * Caption/hashtag üretimi için girdi taşıyıcı (DTO).
 *
 * Ürün bağlamı + marka ton ipucu (palet) caption sürücüsüne buradan akar.
 */
class CaptionRequest
{
    /**
     * @param  string                $productName  Ürün adı
     * @param  string|null           $category     Ürün kategorisi (varsa)
     * @param  array<string,string>  $attributes   Ek ürün nitelikleri (materyal, cinsiyet vb.)
     * @param  array<string,string>  $palette      Marka renk token'ları (ton ipucu)
     */
    public function __construct(
        public string $productName,
        public ?string $category = null,
        public array $attributes = [],
        public array $palette = [],
    ) {}
}
