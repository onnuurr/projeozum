<?php

namespace Modules\Creative\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Creative\Models\CreativeAsset;
use Modules\Creative\Models\GarmentScan;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Notifications\CreativeReviewAlertNotification;
use Modules\Creative\Notifications\CreativeReviewReportReadyNotification;
use Modules\Creative\Services\Ai\Contracts\RejectionInsightContract;
use Modules\Creative\Services\ReviewAlertEvaluator;
use Throwable;

/**
 * Salt-okuma ret analiz raporu: son N günde incelenen manken/giydirme sonuçlarını
 * ret etiketi + AI sürücü/model kırılımında özetler — "en çok nerede hata alıyoruz"
 * sorusuna kod değişikliği yapmadan önce veriyle cevap vermek için.
 *
 * Hiçbir şey SİLMEZ/DEĞİŞTİRMEZ — yalnızca teşhis üretir (bkz. schema:audit deseni).
 * Çıktı: storage/app/creative-review-reports/{tarih}.json + creative.approve'a bildirim.
 */
class CreativeReviewReportCommand extends Command
{
    protected $signature = 'creative:review-report {--days=7 : Kaç günlük pencere taransın} {--no-notify : Bildirim gönderme, yalnızca dosyayı üret}';

    protected $description = 'Son N günde reddedilen manken/giydirme/creative görsellerini etiket ve AI sürücü kırılımında raporlar (salt-okuma)';

    public function __construct(
        private RejectionInsightContract $insightGenerator,
        private ReviewAlertEvaluator $alertEvaluator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days  = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $tryon      = $this->summarizeSubject(TryonResult::query()->where('reviewed_at', '>=', $since)->get(), 'tryon_driver', 'tryon_model');
        $mannequins = $this->summarizeSubject(Mannequin::query()->where('reviewed_at', '>=', $since)->get(), null, null);
        $assets     = $this->summarizeSubject(CreativeAsset::query()->where('reviewed_at', '>=', $since)->get(), null, null);
        $detections = $this->summarizeDetections(GarmentScan::query()->where('created_at', '>=', $since)->get());
        $colorAudit = $this->summarizeColorAudit(TryonResult::query()->where('created_at', '>=', $since)->get());

        $report = [
            'generated_at'        => now()->toIso8601String(),
            'window_days'         => $days,
            'window_since'        => $since->toIso8601String(),
            'tryon_results'       => $tryon,
            'mannequins'          => $mannequins,
            'creative_assets'     => $assets,
            'detection_summary'   => $detections,
            'color_audit_summary' => $colorAudit,
            'ai_insight'          => $this->generateInsight($tryon, $mannequins, $assets),
        ];

        $path = storage_path('app/creative-review-reports/' . now()->format('Y-m-d') . '.json');

        $alerts = $this->alertEvaluator->evaluate([$report, ...$this->loadPreviousReports($path)]);
        $report['alerts'] = $alerts;

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->renderConsole($tryon, $mannequins, $assets, $detections, $colorAudit, $alerts);
        $this->info("Tam rapor: {$path}");

        if (! $this->option('no-notify')) {
            $this->notifyApprovers($tryon, $path);

            if ($alerts !== []) {
                $this->notifyAlert($alerts, $path);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Bugünkü rapor dosyasının (henüz yazılmamış) kardeşlerini — kendisi
     * dışındaki en yeni dosyaları — en yeniden en eskiye sıralı döner.
     * ReviewAlertEvaluator kaç tanesine ihtiyacı olduğuna kendi karar verir
     * (yetersizse metrikleri sessizce atlar), burada sadece elde ne varsa okunur.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadPreviousReports(string $todayPath): array
    {
        $dir = dirname($todayPath);

        if (! File::isDirectory($dir)) {
            return [];
        }

        return collect(File::files($dir))
            ->filter(fn ($f) => preg_match('/^\d{4}-\d{2}-\d{2}\.json$/', $f->getFilename()))
            ->reject(fn ($f) => $f->getPathname() === $todayPath)
            ->sortByDesc(fn ($f) => $f->getFilename())
            ->map(fn ($f) => json_decode(File::get($f->getPathname()), true) ?? [])
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Mannequin|TryonResult|CreativeAsset>  $rows
     * @return array<string,mixed>
     */
    private function summarizeSubject($rows, ?string $driverColumn, ?string $modelColumn): array
    {
        $reviewed = $rows->whereIn('review_status', ['approved', 'rejected']);
        $rejected = $reviewed->where('review_status', 'rejected');

        $tagCounts = [];
        foreach ($rejected as $row) {
            foreach ((array) ($row->review_tags ?? []) as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        arsort($tagCounts);

        $driverBreakdown = [];
        if ($driverColumn !== null) {
            foreach ($reviewed->groupBy($driverColumn) as $driver => $group) {
                $driverBreakdown[$driver ?: 'bilinmiyor'] = [
                    'total'    => $group->count(),
                    'rejected' => $group->where('review_status', 'rejected')->count(),
                    'models'   => $group->pluck($modelColumn)->filter()->unique()->values()->all(),
                ];
            }
        }

        $sampleNotes = $rejected
            ->pluck('review_note')
            ->filter()
            ->take(10)
            ->map(fn ($n) => mb_substr($n, 0, 200))
            ->values()
            ->all();

        return [
            'total_reviewed'   => $reviewed->count(),
            'total_rejected'   => $rejected->count(),
            'rejection_rate'   => $reviewed->count() > 0 ? round($rejected->count() / $reviewed->count(), 3) : null,
            'tag_counts'       => $tagCounts,
            'driver_breakdown' => $driverBreakdown,
            'sample_notes'     => $sampleNotes,
        ];
    }

    /**
     * Giysi parça tespiti (bkz. GarmentScanService) özeti: sıfır-tespitli tarama
     * oranı model eğitim ihtiyacının en doğrudan göstergesidir — model henüz
     * eğitilmediyse (NullGarmentPartDetector) TÜM taramalar bu grupta görünür,
     * bu beklenen ve zararsızdır (bkz. Faz G.1/G.2 — manuel etiketleme devrede).
     *
     * @param  \Illuminate\Support\Collection<int,GarmentScan>  $scans
     * @return array<string,mixed>
     */
    private function summarizeDetections($scans): array
    {
        $totalScans = $scans->count();
        $zeroDetection = $scans->filter(fn (GarmentScan $s) => count($s->detections ?? []) === 0)->count();

        $labelCounts = [];
        $confidences = [];
        // Üç ayrı kova: 'zeroshot' bir insan onaylamadan 'auto' kovasına KARIŞTIRILMAZ
        // — aksi halde rapor, henüz doğrulanmamış AI önerilerini fine-tune modelin
        // gerçek tespitleriymiş gibi gösterirdi (bkz. ROADMAP.md Faz G.3b).
        $bySource = ['manual' => 0, 'auto' => 0, 'zeroshot' => 0];

        foreach ($scans as $scan) {
            foreach ((array) ($scan->detections ?? []) as $d) {
                $label = $d['label_display'] ?? null;
                if ($label) {
                    $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;
                }
                if (isset($d['confidence'])) {
                    $confidences[] = (float) $d['confidence'];
                }
                $source = $d['source'] ?? 'auto';
                $bySource[$source] = ($bySource[$source] ?? 0) + 1;
            }
        }
        arsort($labelCounts);

        return [
            'total_scans'                => $totalScans,
            'scans_with_zero_detections' => $zeroDetection,
            'zero_detection_rate'        => $totalScans > 0 ? round($zeroDetection / $totalScans, 3) : null,
            'label_frequency'            => $labelCounts,
            'avg_confidence'             => $confidences !== [] ? round(array_sum($confidences) / count($confidences), 3) : null,
            'by_source'                  => $bySource,
        ];
    }

    /**
     * Renk sadakati (Faz Q) özeti: ortalama Delta E, uyarı eşiği üstü oran, güvenli
     * ölçülemeyen (maske bulunamadı/kirli) oran. `creative.color_fidelity.audit.enabled`
     * kapalıyken (varsayılan) `meta.color_audit` hiçbir kayıtta yok — bu durumda
     * `total_measured=0` döner, UI/rapor bunu "denetim kapalı" olarak yorumlar.
     *
     * @param  \Illuminate\Support\Collection<int,TryonResult>  $results
     * @return array<string,mixed>
     */
    private function summarizeColorAudit($results): array
    {
        $warnDeltaE = (float) config('creative.color_fidelity.audit.warn_delta_e', 10.0);

        $measured = $results
            ->map(fn (TryonResult $r) => $r->meta['color_audit'] ?? null)
            ->filter();

        $highConfidence = $measured->filter(fn ($a) => ($a['confidence'] ?? null) === 'high' && $a['delta_e'] !== null);
        $deltaEs        = $highConfidence->pluck('delta_e')->map(fn ($v) => (float) $v);
        $overWarn       = $deltaEs->filter(fn ($v) => $v > $warnDeltaE)->count();
        $lockedCount    = $measured->filter(fn ($a) => (bool) ($a['locked'] ?? false))->count();

        return [
            'total_measured'       => $measured->count(),
            'high_confidence'      => $highConfidence->count(),
            'avg_delta_e'          => $deltaEs->isNotEmpty() ? round($deltaEs->avg(), 2) : null,
            'over_warn_threshold'  => $overWarn,
            'over_warn_rate'       => $highConfidence->count() > 0 ? round($overWarn / $highConfidence->count(), 3) : null,
            'locked_count'         => $lockedCount,
            'warn_delta_e'         => $warnDeltaE,
        ];
    }

    /**
     * AI önerisi rapor üretimi sırasında BİR KEZ üretilip JSON'a gömülür (sayfa
     * her açıldığında tekrar çağrılmaz). Sürücü (Gemini) çağrısı başarısız olursa
     * asıl rapor verisi yine de kaydedilsin diye hata burada yutulur.
     */
    private function generateInsight(array $tryon, array $mannequins, array $assets): ?string
    {
        try {
            return $this->insightGenerator->generate($tryon, $mannequins, $assets);
        } catch (Throwable $e) {
            $this->components->warn("AI önerisi üretilemedi: {$e->getMessage()}");

            return null;
        }
    }

    private function renderConsole(array $tryon, array $mannequins, array $assets, array $detections, array $colorAudit = [], array $alerts = []): void
    {
        if ($alerts !== []) {
            $this->newLine();
            $this->components->warn(sprintf('%d eşik/uyarı tetiklendi:', count($alerts)));
            foreach ($alerts as $alert) {
                $this->line(sprintf(
                    '  ⚠ %s → %s: %%%d (eşik %%%d, %d gündür)',
                    $alert['subject_label'],
                    $alert['label'],
                    round($alert['rate'] * 100),
                    round($alert['threshold'] * 100),
                    $alert['streak'],
                ));
            }
        }

        $this->newLine();
        $this->components->info('Giydirme (TryonResult) ret özeti');
        $this->line("  İncelenen: {$tryon['total_reviewed']}  |  Reddedilen: {$tryon['total_rejected']}"
            . ($tryon['rejection_rate'] !== null ? sprintf(' (%%%d)', $tryon['rejection_rate'] * 100) : ''));

        if ($tryon['tag_counts'] !== []) {
            $this->table(['Ret etiketi', 'Adet'], collect($tryon['tag_counts'])->map(fn ($c, $t) => [$t, $c])->values()->all());
        }

        if ($tryon['driver_breakdown'] !== []) {
            $this->table(
                ['Sürücü', 'Toplam', 'Reddedilen', 'Modeller'],
                collect($tryon['driver_breakdown'])->map(fn ($d, $name) => [
                    $name, $d['total'], $d['rejected'], implode(', ', $d['models']),
                ])->values()->all(),
            );
        }

        $this->newLine();
        $this->components->info('Manken ret özeti');
        $this->line("  İncelenen: {$mannequins['total_reviewed']}  |  Reddedilen: {$mannequins['total_rejected']}");
        if ($mannequins['tag_counts'] !== []) {
            $this->table(['Ret etiketi', 'Adet'], collect($mannequins['tag_counts'])->map(fn ($c, $t) => [$t, $c])->values()->all());
        }

        $this->newLine();
        $this->components->info('Creative Studio (sosyal medya tasarımı) ret özeti');
        $this->line("  İncelenen: {$assets['total_reviewed']}  |  Reddedilen: {$assets['total_rejected']}"
            . ($assets['rejection_rate'] !== null ? sprintf(' (%%%d)', $assets['rejection_rate'] * 100) : ''));
        if ($assets['tag_counts'] !== []) {
            $this->table(['Ret etiketi', 'Adet'], collect($assets['tag_counts'])->map(fn ($c, $t) => [$t, $c])->values()->all());
        }

        $this->newLine();
        $this->components->info('Giysi parça tespiti özeti');
        $this->line("  Tarama: {$detections['total_scans']}  |  Sıfır tespitli: {$detections['scans_with_zero_detections']}"
            . ($detections['zero_detection_rate'] !== null ? sprintf(' (%%%d)', $detections['zero_detection_rate'] * 100) : ''));
        if ($detections['label_frequency'] !== []) {
            $this->table(['Parça', 'Adet'], collect($detections['label_frequency'])->map(fn ($c, $t) => [$t, $c])->values()->all());
        }

        if (($colorAudit['total_measured'] ?? 0) > 0) {
            $this->newLine();
            $this->components->info('Renk sadakati özeti (Faz Q)');
            $this->line("  Ölçülen: {$colorAudit['total_measured']}  |  Güvenli: {$colorAudit['high_confidence']}"
                . '  |  Ort. ΔE: ' . ($colorAudit['avg_delta_e'] ?? '—')
                . "  |  Eşik ({$colorAudit['warn_delta_e']}) üstü: {$colorAudit['over_warn_threshold']}"
                . ($colorAudit['over_warn_rate'] !== null ? sprintf(' (%%%d)', $colorAudit['over_warn_rate'] * 100) : ''));
        }
    }

    private function notifyApprovers(array $tryon, string $path): void
    {
        $topTag      = array_key_first($tryon['tag_counts']);
        $topTagCount = $topTag !== null ? $tryon['tag_counts'][$topTag] : 0;

        $notification = new CreativeReviewReportReadyNotification(
            rejectedCount:      $tryon['total_rejected'],
            totalReviewedCount: $tryon['total_reviewed'],
            topTag:             $topTag,
            topTagCount:        $topTagCount,
            reportPath:         $path,
        );

        foreach (User::permission('creative.approve')->get() as $admin) {
            $admin->notify($notification);
        }
    }

    /**
     * @param  array<int,array{subject:string,subject_label:string,type:string,label:string,rate:float,threshold:float,streak:int,sample_size:int}>  $alerts
     */
    private function notifyAlert(array $alerts, string $path): void
    {
        $notification = new CreativeReviewAlertNotification($alerts, $path);

        foreach (User::permission('creative.approve')->get() as $admin) {
            $admin->notify($notification);
        }
    }
}
