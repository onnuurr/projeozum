<?php

namespace Modules\Product\Services\Ai;

final class ProductDescriptionResult
{
    public function __construct(
        public readonly string $publicDescription,
        public readonly string $tenantDescription,
        public readonly string $model,
    ) {}

    public function toArray(): array
    {
        return [
            'public_description' => $this->publicDescription,
            'tenant_description' => $this->tenantDescription,
            'model'              => $this->model,
        ];
    }
}
