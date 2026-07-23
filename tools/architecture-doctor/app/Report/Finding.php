<?php

namespace ArchitectureDoctor\Report;

final class Finding
{
    public function __construct(
        public readonly string $message,
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly ?string $suggestion = null,
    ) {}
}
