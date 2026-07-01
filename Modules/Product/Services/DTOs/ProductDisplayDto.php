<?php

namespace Modules\Product\Services\DTOs;

final class ProductDisplayDto
{
    public const SOURCE_DEFAULT        = 'default';
    public const SOURCE_TENANT_OVERRIDE = 'tenant_override';

    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $source,
    ) {}

    public function toArray(): array
    {
        return [
            'name'        => $this->name,
            'description' => $this->description,
            'source'      => $this->source,
        ];
    }
}
