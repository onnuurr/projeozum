<?php

namespace ArchitectureDoctor\Policies;

use ArchitectureDoctor\Engine\AnalysisReport;

/**
 * Rule'lar sadece "ne bulundu"yu (Finding) rapor eder; "bunu ne yapalım" kararı
 * (CI'ı durdur mu, sadece uyar mı) kasıtlı olarak Rule'un dışında, burada verilir.
 * Böylece aynı Rule farklı bağlamlarda farklı şekilde uygulanabilir.
 */
final class Policy
{
    public function exitCode(AnalysisReport $report, ExecutionContext $context): int
    {
        if ($context === ExecutionContext::Ci && $report->hasBlockingFailures()) {
            return 1;
        }

        return 0;
    }
}
