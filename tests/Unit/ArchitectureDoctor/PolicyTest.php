<?php

namespace Tests\Unit\ArchitectureDoctor;

use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Engine\AnalysisReport;
use ArchitectureDoctor\Policies\ExecutionContext;
use ArchitectureDoctor\Policies\Policy;
use ArchitectureDoctor\Report\Finding;
use ArchitectureDoctor\Report\RuleResult;
use PHPUnit\Framework\TestCase;

class PolicyTest extends TestCase
{
    private function resultWith(Severity $severity, array $findings, Maturity $maturity = Maturity::Stable): RuleResult
    {
        return new RuleResult(
            ruleId: 'fake.rule',
            category: 'Fake',
            severity: $severity,
            lifecycle: new Lifecycle($maturity, '2026-07-22'),
            findings: $findings,
        );
    }

    public function test_local_context_never_blocks_regardless_of_severity(): void
    {
        $report = new AnalysisReport([
            $this->resultWith(Severity::Critical, [new Finding('kritik ihlal')]),
        ]);

        $this->assertSame(0, (new Policy)->exitCode($report, ExecutionContext::Local));
    }

    public function test_ci_context_blocks_on_critical_failure(): void
    {
        $report = new AnalysisReport([
            $this->resultWith(Severity::Critical, [new Finding('kritik ihlal')]),
        ]);

        $this->assertSame(1, (new Policy)->exitCode($report, ExecutionContext::Ci));
    }

    public function test_ci_context_does_not_block_on_warning_failure(): void
    {
        $report = new AnalysisReport([
            $this->resultWith(Severity::Warning, [new Finding('sadece uyarı')]),
        ]);

        $this->assertSame(0, (new Policy)->exitCode($report, ExecutionContext::Ci));
    }

    public function test_ci_context_does_not_block_when_everything_passes(): void
    {
        $report = new AnalysisReport([
            $this->resultWith(Severity::Critical, []),
        ]);

        $this->assertSame(0, (new Policy)->exitCode($report, ExecutionContext::Ci));
    }

    public function test_ci_context_does_not_block_on_experimental_critical_failure(): void
    {
        $report = new AnalysisReport([
            $this->resultWith(Severity::Critical, [new Finding('yeni kural, henüz olgunlaşmadı')], Maturity::Experimental),
        ]);

        $this->assertSame(0, (new Policy)->exitCode($report, ExecutionContext::Ci));
    }
}
