<?php

namespace ArchitectureDoctor\Report;

use ArchitectureDoctor\Engine\AnalysisReport;

final class JsonFormatter
{
    public function toArray(AnalysisReport $report): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'results' => array_map(
                fn (RuleResult $result): array => [
                    'rule_id' => $result->ruleId,
                    'category' => $result->category,
                    'severity' => $result->severity->value,
                    'lifecycle' => [
                        'maturity' => $result->lifecycle->maturity->value,
                        'introduced_in' => $result->lifecycle->introducedIn,
                    ],
                    'passed' => $result->passed(),
                    'findings' => array_map(
                        fn (Finding $finding): array => [
                            'message' => $finding->message,
                            'file' => $finding->file,
                            'line' => $finding->line,
                            'suggestion' => $finding->suggestion,
                        ],
                        $result->findings,
                    ),
                ],
                $report->results,
            ),
            'category_summaries' => $report->categorySummaries(),
        ];
    }

    public function toJson(AnalysisReport $report): string
    {
        return json_encode(
            $this->toArray($report),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
