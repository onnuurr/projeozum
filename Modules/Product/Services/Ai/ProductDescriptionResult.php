<?php

namespace Modules\Product\Services\Ai;

final class ProductDescriptionResult
{
    public function __construct(
        public readonly string $publicDescription,
        public readonly string $tenantDescription,
        public readonly string $model,
        // SEO alanları — üretici doldurmazsa null (geriye uyumlu).
        public readonly ?string $publicName = null,
        public readonly ?string $metaTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $metaKeywords = null,
    ) {}

    public function toArray(): array
    {
        return [
            'public_description' => $this->publicDescription,
            'tenant_description' => $this->tenantDescription,
            'model'              => $this->model,
            'public_name'        => $this->publicName,
            'meta_title'         => $this->metaTitle,
            'meta_description'   => $this->metaDescription,
            'meta_keywords'      => $this->metaKeywords,
        ];
    }
}
