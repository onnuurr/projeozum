<?php

namespace ArchitectureDoctor\Contracts;

/**
 * Bir Rule'un olgunluk düzeyi. `Maturity::Experimental` kurallar auto-discovery ile
 * bulunur ve raporlanır, ama Policy katmanında henüz CI'ı bloklamaz — bkz. Policy.
 */
final class Lifecycle
{
    public function __construct(
        public readonly Maturity $maturity,
        public readonly string $introducedIn,
    ) {}
}
