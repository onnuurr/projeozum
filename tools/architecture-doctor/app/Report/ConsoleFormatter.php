<?php

namespace ArchitectureDoctor\Report;

use ArchitectureDoctor\Engine\AnalysisReport;

final class ConsoleFormatter
{
    public function format(AnalysisReport $report): string
    {
        $lines = ['Architecture Doctor Report', str_repeat('-', 40)];

        foreach ($report->results as $result) {
            $status = $result->passed() ? 'PASSED' : 'FAILED';

            $lines[] = sprintf(
                '[%s] %s (%s/%s, %s)',
                $status,
                $result->ruleId,
                $result->category,
                $result->severity->value,
                $result->lifecycle->maturity->value,
            );

            foreach ($result->findings as $finding) {
                $location = $finding->file
                    ? sprintf(' (%s%s)', $finding->file, $finding->line ? ':'.$finding->line : '')
                    : '';

                $lines[] = sprintf('  - %s%s', $finding->message, $location);
            }
        }

        $lines[] = str_repeat('-', 40);

        foreach ($report->categorySummaries() as $category => $summary) {
            $lines[] = sprintf('%s: %d/%d passed', $category, $summary['passed'], $summary['total']);
        }

        return implode(PHP_EOL, $lines);
    }
}
