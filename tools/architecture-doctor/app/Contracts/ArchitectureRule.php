<?php

namespace ArchitectureDoctor\Contracts;

use ArchitectureDoctor\Report\Finding;

/**
 * Auto-discovery bu contract'ı implement eden sınıfları bulur (dosya adı desenine göre değil).
 * Bkz. Engine\RuleDiscovery.
 */
interface ArchitectureRule
{
    public function id(): string;

    public function category(): string;

    public function severity(): Severity;

    public function lifecycle(): Lifecycle;

    /**
     * @return Finding[] Boş dizi = kural geçti.
     */
    public function check(): array;
}
