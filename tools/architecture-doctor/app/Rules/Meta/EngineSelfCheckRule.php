<?php

namespace ArchitectureDoctor\Rules\Meta;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;

/**
 * Gerçek bir mimari denetim değil — auto-discovery + execution + reporting hattının
 * uçtan uca çalıştığını doğrulamak için tek self-test kuralı. Faz 1+ gerçek kuralları
 * (Deptrac, migration disiplini, AI contract, tenant izolasyonu) bunun yerini almaz,
 * yanına eklenir.
 */
final class EngineSelfCheckRule implements ArchitectureRule
{
    public function id(): string
    {
        return 'engine.self-check';
    }

    public function category(): string
    {
        return 'Engine';
    }

    public function severity(): Severity
    {
        return Severity::Notice;
    }

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle(Maturity::Experimental, '2026-07-22');
    }

    public function check(): array
    {
        return [];
    }
}
