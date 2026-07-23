<?php

namespace ArchitectureDoctor\Engine;

use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\RuleResult;

final class AnalysisReport
{
    /**
     * @param  RuleResult[]  $results
     */
    public function __construct(public readonly array $results) {}

    /**
     * @return array<string, array{total: int, passed: int, failed: int}>
     */
    public function categorySummaries(): array
    {
        $summaries = [];

        foreach ($this->results as $result) {
            $summaries[$result->category] ??= ['total' => 0, 'passed' => 0, 'failed' => 0];
            $summaries[$result->category]['total']++;
            $summaries[$result->category][$result->passed() ? 'passed' : 'failed']++;
        }

        return $summaries;
    }

    /**
     * Experimental kurallar hiçbir zaman bloklamaz — bkz. lifecycle metadata. Bir kural
     * ancak Stable'a yükseltildikten sonra, Critical + başarısız bir sonucu varsa CI'ı durdurur.
     */
    public function hasBlockingFailures(): bool
    {
        foreach ($this->results as $result) {
            if (
                ! $result->passed()
                && $result->severity === Severity::Critical
                && $result->lifecycle->maturity !== Maturity::Experimental
            ) {
                return true;
            }
        }

        return false;
    }
}
