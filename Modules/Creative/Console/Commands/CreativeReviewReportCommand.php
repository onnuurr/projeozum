<?php

namespace Modules\Creative\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Notifications\CreativeReviewReportReadyNotification;

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

    protected $description = 'Son N günde reddedilen manken/giydirme görsellerini etiket ve AI sürücü kırılımında raporlar (salt-okuma)';

    public function handle(): int
    {
        $days  = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $tryon      = $this->summarizeSubject(TryonResult::query()->where('reviewed_at', '>=', $since)->get(), 'tryon_driver', 'tryon_model');
        $mannequins = $this->summarizeSubject(Mannequin::query()->where('reviewed_at', '>=', $since)->get(), null, null);

        $report = [
            'generated_at'   => now()->toIso8601String(),
            'window_days'    => $days,
            'window_since'   => $since->toIso8601String(),
            'tryon_results'  => $tryon,
            'mannequins'     => $mannequins,
        ];

        $path = storage_path('app/creative-review-reports/' . now()->format('Y-m-d') . '.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->renderConsole($tryon, $mannequins);
        $this->info("Tam rapor: {$path}");

        if (! $this->option('no-notify')) {
            $this->notifyApprovers($tryon, $path);
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Mannequin|TryonResult>  $rows
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

    private function renderConsole(array $tryon, array $mannequins): void
    {
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
}
