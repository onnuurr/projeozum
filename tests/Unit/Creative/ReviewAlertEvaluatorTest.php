<?php

namespace Tests\Unit\Creative;

use Modules\Creative\Services\ReviewAlertEvaluator;
use Tests\TestCase;

/**
 * ReviewAlertEvaluator'ın "tek raporluk sıçrama gürültüdür, ART ARDA N raporda
 * eşik aşılması gerekir" kuralını doğrular (bkz. CreativeReviewReportCommand).
 * config() eriştiği için (review_alerts.*) uygulamayı boot eden TestCase.
 */
class ReviewAlertEvaluatorTest extends TestCase
{
    private function evaluator(): ReviewAlertEvaluator
    {
        return new ReviewAlertEvaluator();
    }

    /**
     * @param  array<string,int>  $tagCounts
     * @return array<string,mixed>
     */
    private function report(float $tryonRate, int $tryonReviewed, array $tagCounts = []): array
    {
        return [
            'tryon_results' => [
                'total_reviewed' => $tryonReviewed,
                'rejection_rate' => $tryonRate,
                'tag_counts'     => $tagCounts,
            ],
            'mannequins' => [
                'total_reviewed' => 0,
                'rejection_rate' => null,
                'tag_counts'     => [],
            ],
            'creative_assets' => [
                'total_reviewed' => 0,
                'rejection_rate' => null,
                'tag_counts'     => [],
            ],
            'detection_summary' => [
                'total_scans'         => 0,
                'zero_detection_rate' => null,
            ],
            'color_audit_summary' => [
                'high_confidence'  => 0,
                'over_warn_rate'   => null,
            ],
        ];
    }

    public function test_triggers_alert_when_rejection_rate_breaches_threshold_for_consecutive_reports(): void
    {
        $window = [
            $this->report(0.40, 10),
            $this->report(0.45, 12),
            $this->report(0.50, 8),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $hit = collect($alerts)->firstWhere('subject', 'tryon_results');
        $this->assertNotNull($hit);
        $this->assertSame('rejection_rate', $hit['type']);
        $this->assertSame(0.40, $hit['rate']);
        $this->assertSame(3, $hit['streak']);
    }

    public function test_does_not_trigger_when_any_report_in_window_is_below_threshold(): void
    {
        $window = [
            $this->report(0.40, 10),
            $this->report(0.10, 12),
            $this->report(0.50, 8),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $this->assertNull(collect($alerts)->firstWhere('subject', 'tryon_results'));
    }

    public function test_does_not_trigger_when_sample_size_is_below_minimum(): void
    {
        $window = [
            $this->report(0.50, 2),
            $this->report(0.50, 3),
            $this->report(0.50, 1),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $this->assertSame([], $alerts);
    }

    public function test_does_not_trigger_with_insufficient_report_history(): void
    {
        $window = [
            $this->report(0.90, 10),
            $this->report(0.90, 10),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $this->assertSame([], $alerts);
    }

    public function test_triggers_tag_level_alert_when_tag_share_breaches_threshold_consistently(): void
    {
        $window = [
            $this->report(0.10, 10, ['renk kayması' => 3]),
            $this->report(0.10, 10, ['renk kayması' => 3]),
            $this->report(0.10, 10, ['renk kayması' => 3]),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $hit = collect($alerts)->firstWhere('label', 'Ret etiketi: renk kayması');
        $this->assertNotNull($hit);
        $this->assertSame('tag_rejection_rate', $hit['type']);
        $this->assertSame('tryon_results', $hit['subject']);
    }

    public function test_does_not_trigger_tag_level_alert_when_an_older_report_lacks_the_tag(): void
    {
        $window = [
            $this->report(0.10, 10, ['renk kayması' => 3]),
            $this->report(0.10, 10, ['renk kayması' => 3]),
            $this->report(0.10, 10, []),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $this->assertNull(collect($alerts)->firstWhere('label', 'Ret etiketi: renk kayması'));
    }

    public function test_does_not_trigger_anything_when_review_alerts_disabled(): void
    {
        config(['creative.review_alerts.enabled' => false]);

        $window = [
            $this->report(0.90, 10),
            $this->report(0.90, 10),
            $this->report(0.90, 10),
        ];

        $alerts = $this->evaluator()->evaluate($window);

        $this->assertSame([], $alerts);
    }
}
