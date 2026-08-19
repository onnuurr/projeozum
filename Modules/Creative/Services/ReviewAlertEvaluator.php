<?php

namespace Modules\Creative\Services;

use Illuminate\Support\Arr;

/**
 * `creative:review-report`'un ürettiği ardışık rapor JSON'larını (en yeniden en
 * eskiye) tarayıp bir ret sinyalinin (genel ret oranı, tek bir ret etiketi, sıfır
 * tespit oranı, renk sapma oranı) ART ARDA `review_alerts.consecutive_reports`
 * raporda eşiği aştığını tespit eder. Amaç: raporu her gün elle açıp
 * "kötüleşiyor mu" diye kontrol etme ihtiyacını otomatikleştirmek — kod/prompt
 * değişikliği (ör. GeminiTryOnPromptBuilder) yine insanda kalır, bu servis
 * yalnızca "hangi rapora bakmalıyım" sinyalini üretir.
 *
 * Saf/IO'suz: dosya okuma CreativeReviewReportCommand'da, karar burada. Tek
 * raporluk sıçramalar gürültü sayılır — bir metrik ancak VERİLEN TÜM raporlarda
 * eşiği aşmış VE min_sample_size'ı sağlamışsa tetiklenir. Yeterli geçmiş rapor
 * yoksa (çağıran daha az rapor verirse) o metrik sessizce atlanır.
 */
class ReviewAlertEvaluator
{
    /**
     * @var array<int,array{subject:string,label:string,rate_path:array<int,string>,sample_path:array<int,string>,threshold_key:string}>
     */
    private const SCALAR_METRICS = [
        [
            'subject'       => 'tryon_results',
            'label'         => 'Giydirme',
            'rate_path'     => ['tryon_results', 'rejection_rate'],
            'sample_path'   => ['tryon_results', 'total_reviewed'],
            'threshold_key' => 'rejection_rate',
        ],
        [
            'subject'       => 'mannequins',
            'label'         => 'Manken',
            'rate_path'     => ['mannequins', 'rejection_rate'],
            'sample_path'   => ['mannequins', 'total_reviewed'],
            'threshold_key' => 'rejection_rate',
        ],
        [
            'subject'       => 'creative_assets',
            'label'         => 'Creative Studio',
            'rate_path'     => ['creative_assets', 'rejection_rate'],
            'sample_path'   => ['creative_assets', 'total_reviewed'],
            'threshold_key' => 'rejection_rate',
        ],
        [
            'subject'       => 'detection_summary',
            'label'         => 'Giysi parça tespiti',
            'rate_path'     => ['detection_summary', 'zero_detection_rate'],
            'sample_path'   => ['detection_summary', 'total_scans'],
            'threshold_key' => 'zero_detection_rate',
        ],
        [
            'subject'       => 'color_audit_summary',
            'label'         => 'Renk sadakati',
            'rate_path'     => ['color_audit_summary', 'over_warn_rate'],
            'sample_path'   => ['color_audit_summary', 'high_confidence'],
            'threshold_key' => 'color_over_warn_rate',
        ],
    ];

    /**
     * @var array<int,array{subject:string,label:string}>
     */
    private const TAG_SUBJECTS = [
        ['subject' => 'tryon_results', 'label' => 'Giydirme'],
        ['subject' => 'mannequins', 'label' => 'Manken'],
        ['subject' => 'creative_assets', 'label' => 'Creative Studio'],
    ];

    /**
     * @param  array<int,array<string,mixed>>  $reports  En yeniden en eskiye sıralı, decode edilmiş rapor JSON'ları (bugünkü dahil).
     * @return array<int,array{subject:string,subject_label:string,type:string,label:string,rate:float,threshold:float,streak:int,sample_size:int}>
     */
    public function evaluate(array $reports): array
    {
        if (! config('creative.review_alerts.enabled') || $reports === []) {
            return [];
        }

        $needed = max(1, (int) config('creative.review_alerts.consecutive_reports', 3));

        if (count($reports) < $needed) {
            return [];
        }

        $window   = array_slice($reports, 0, $needed);
        $minSample = (int) config('creative.review_alerts.min_sample_size', 5);
        $alerts   = [];

        foreach (self::SCALAR_METRICS as $metric) {
            $threshold = (float) config('creative.review_alerts.thresholds.' . $metric['threshold_key']);
            $hit = $this->checkSeries(
                $window,
                fn (array $report) => Arr::get($report, implode('.', $metric['rate_path'])),
                fn (array $report) => (int) Arr::get($report, implode('.', $metric['sample_path']), 0),
                $threshold,
                $minSample,
            );

            if ($hit !== null) {
                $alerts[] = [
                    'subject'       => $metric['subject'],
                    'subject_label' => $metric['label'],
                    'type'          => $metric['threshold_key'],
                    'label'         => $this->scalarLabel($metric['threshold_key']),
                    'rate'          => $hit['rate'],
                    'threshold'     => $threshold,
                    'streak'        => $needed,
                    'sample_size'   => $hit['sample_size'],
                ];
            }
        }

        $latest = $window[0];

        foreach (self::TAG_SUBJECTS as $tagSubject) {
            $tagCounts = Arr::get($latest, $tagSubject['subject'] . '.tag_counts', []);
            $threshold = (float) config('creative.review_alerts.thresholds.tag_rejection_rate');

            foreach (array_keys($tagCounts) as $tag) {
                $hit = $this->checkSeries(
                    $window,
                    function (array $report) use ($tagSubject, $tag) {
                        $reviewed = (int) Arr::get($report, $tagSubject['subject'] . '.total_reviewed', 0);

                        if ($reviewed === 0) {
                            return null;
                        }

                        $count = (int) Arr::get($report, $tagSubject['subject'] . '.tag_counts.' . $tag, 0);

                        return $count / $reviewed;
                    },
                    fn (array $report) => (int) Arr::get($report, $tagSubject['subject'] . '.total_reviewed', 0),
                    $threshold,
                    $minSample,
                );

                if ($hit !== null) {
                    $alerts[] = [
                        'subject'       => $tagSubject['subject'],
                        'subject_label' => $tagSubject['label'],
                        'type'          => 'tag_rejection_rate',
                        'label'         => "Ret etiketi: {$tag}",
                        'rate'          => $hit['rate'],
                        'threshold'     => $threshold,
                        'streak'        => $needed,
                        'sample_size'   => $hit['sample_size'],
                    ];
                }
            }
        }

        return $alerts;
    }

    /**
     * $window'daki HER raporda oran > eşik VE örneklem >= min ise en güncel
     * raporun (rate, sample_size) çiftini döner; tek rapor bile şartı
     * sağlamazsa (veya oran/eşik null/bilinmiyorsa) null döner.
     *
     * @param  array<int,array<string,mixed>>  $window
     * @param  callable(array<string,mixed>):?float  $rateOf
     * @param  callable(array<string,mixed>):int  $sampleOf
     * @return array{rate:float,sample_size:int}|null
     */
    private function checkSeries(array $window, callable $rateOf, callable $sampleOf, float $threshold, int $minSample): ?array
    {
        $latestRate = null;
        $latestSample = 0;

        foreach ($window as $i => $report) {
            $rate   = $rateOf($report);
            $sample = $sampleOf($report);

            if ($rate === null || $sample < $minSample || $rate <= $threshold) {
                return null;
            }

            if ($i === 0) {
                $latestRate   = $rate;
                $latestSample = $sample;
            }
        }

        return $latestRate === null ? null : ['rate' => $latestRate, 'sample_size' => $latestSample];
    }

    private function scalarLabel(string $thresholdKey): string
    {
        return match ($thresholdKey) {
            'rejection_rate'       => 'Genel ret oranı',
            'zero_detection_rate'  => 'Sıfır tespit oranı',
            'color_over_warn_rate' => 'Delta E eşik üstü oranı',
            default                => $thresholdKey,
        };
    }
}
