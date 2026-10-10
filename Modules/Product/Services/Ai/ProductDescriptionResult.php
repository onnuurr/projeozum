<?php

namespace Modules\Product\Services\Ai;

/**
 * AI ürün içeriği üretiminin sonucu. İstenmeyen bölümün alanları null'dır; SEO alanları
 * ProductSeoRules'tan geçmiş (uzunluk/biçim zorlanmış) değerlerdir.
 */
final class ProductDescriptionResult
{
    public function __construct(
        public readonly ?string $publicDescription,
        public readonly ?string $tenantDescription,
        public readonly string $model,
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $metaKeywords = null,
        public readonly string $promptVersion = ProductDescriptionPromptBuilder::PROMPT_VERSION,
        public readonly ?string $publicName = null,
    ) {}

    public function toArray(): array
    {
        return [
            'public_name'        => $this->publicName,
            'public_description' => $this->publicDescription,
            'tenant_description' => $this->tenantDescription,
            'meta_title'         => $this->metaTitle,
            'meta_description'   => $this->metaDescription,
            'meta_keywords'      => $this->metaKeywords,
            'model'              => $this->model,
            'prompt_version'     => $this->promptVersion,
        ];
    }
}
