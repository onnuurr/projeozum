<?php

namespace Modules\Superadmin\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Modules\Superadmin\Contracts\ArchitectureDoctorFixerRunner;
use Modules\Superadmin\Models\ArchitectureDoctorFixRun;
use RuntimeException;
use Throwable;

/**
 * Panelden tetiklenen "Otomatik Düzelt" akışını yürütür: canlı checkout'a hiç
 * dokunmadan izole bir git worktree açar, Mimari Doktor'un before/after
 * özetini yakalar, agent'ı (ArchitectureDoctorFixerRunner) çalıştırır ve
 * yapılan değişiklikleri yeni bir branch'e commit'ler — push/merge yapmaz,
 * inceleme ve devreye alma insana kalır.
 *
 * `storage/` kasıtlı olarak symlink'lenmez: bu repoda storage/ zaten tracked
 * bir iskelet (her alt dizinde `.gitignore` marker dosyası — bkz. storage/app/
 * .gitignore) olduğundan `git worktree add` onu zaten checkout eder; ayrıca
 * `storage/` gibi bir dizini symlink'lemek git'in ".gitignore'daki `foo/`
 * deseni bir symlink'i eşlemez" davranışı yüzünden `git status`in symlink'i
 * "değişiklik" sanmasına yol açar (bkz. ArchitectureDoctorFixServiceTest'teki
 * regresyon notu). `.env`/`vendor`/`node_modules` ise gerçekten tracked
 * değildir ve dosya/trailing-slash'sız desenlerdir, bu yüzden güvenle
 * symlink'lenebilir.
 */
class ArchitectureDoctorFixService
{
    private const SHARED_LINKS = ['.env', 'vendor', 'node_modules'];

    private const EXCLUDE_AGENTS_PATHSPEC = ':(exclude).claude/agents';

    public function __construct(private ArchitectureDoctorFixerRunner $runner) {}

    public function run(ArchitectureDoctorFixRun $fixRun): void
    {
        $fixRun->update(['status' => ArchitectureDoctorFixRun::STATUS_RUNNING, 'started_at' => now()]);

        $basePath = rtrim((string) config('superadmin.architecture_doctor.repo_path'), '/');
        $worktreeBase = rtrim((string) config('superadmin.architecture_doctor.worktree_base_path'), '/');
        $branch = 'architecture-doctor-fix/'.now()->format('Ymd_His');
        $worktreePath = "{$worktreeBase}/{$fixRun->id}-".now()->format('YmdHis');

        File::ensureDirectoryExists($worktreeBase);
        $this->git($basePath, ['worktree', 'prune']);

        $worktreeCreated = false;
        $succeededNoChanges = false;

        try {
            $baseCommitSha = trim($this->git($basePath, ['rev-parse', 'HEAD'])->output());

            $addResult = $this->git($basePath, ['worktree', 'add', $worktreePath, '-b', $branch, 'HEAD']);
            if (! $addResult->successful()) {
                throw new RuntimeException("git worktree add başarısız: {$addResult->errorOutput()}");
            }
            $worktreeCreated = true;

            $fixRun->update(['branch_name' => $branch, 'base_commit_sha' => $baseCommitSha]);

            $this->linkSharedPaths($basePath, $worktreePath);

            $rulesBefore = $this->captureDoctorSummary($worktreePath);

            $prompt = "Mimari Doktor raporundaki FAILED kuralları architecture-doctor-fixer agent'ını "
                .'kullanarak triage et ve güvenli olanları düzelt.';
            $result = $this->runner->run($worktreePath, $prompt);
            if ($result['exitCode'] !== 0) {
                throw new RuntimeException("Fixer agent başarısız (exit {$result['exitCode']}): {$result['output']}");
            }

            $rulesAfter = $this->captureDoctorSummary($worktreePath);

            $filesChanged = $this->commitIfChanged($worktreePath, $fixRun->id);
            $succeededNoChanges = $filesChanged === [];

            $fixRun->update([
                'status' => ArchitectureDoctorFixRun::STATUS_SUCCESS,
                'finished_at' => now(),
                'duration_seconds' => (int) round(abs(now()->diffInSeconds($fixRun->started_at))),
                'rules_before' => $rulesBefore,
                'rules_after' => $rulesAfter,
                'files_changed' => $filesChanged,
                'log_output' => $result['output'],
            ]);
        } catch (Throwable $e) {
            $fixRun->update([
                'status' => ArchitectureDoctorFixRun::STATUS_FAILED,
                'finished_at' => now(),
                'duration_seconds' => (int) round(abs(now()->diffInSeconds($fixRun->started_at))),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($worktreeCreated) {
                $this->git($basePath, ['worktree', 'remove', '--force', $worktreePath]);

                // Değişiklik yoksa boş branch tutmaya gerek yok — worktree kaldırıldıktan
                // SONRA silinmeli, aksi halde git "branch bir worktree'de checkout'lu" der.
                if ($succeededNoChanges) {
                    $this->git($basePath, ['branch', '-D', $branch]);
                }
            }
        }
    }

    /**
     * `git status --porcelain` boşsa değişiklik yoktur — branch boş bırakılmaz,
     * silinir. Doluysa commit'lenir; asıl branch'e push/merge YAPILMAZ.
     *
     * `.claude/agents` pathspec exclusion ile hariç tutulur: canlı repoda henüz
     * commit'lenmemiş (untracked ama gitignore'lu DA değil) olduğundan, worktree
     * içinde symlink'lendiğinde git onu "yeni dosya" sanıp buraya sızdırabiliyordu
     * (gerçek bir kod değişikliği yokken bile) — manuel doğrulamada yakalanan
     * regresyon, bkz. ArchitectureDoctorFixServiceTest.
     *
     * @return array<int,string>
     */
    private function commitIfChanged(string $worktreePath, int $fixRunId): array
    {
        $status = $this->git($worktreePath, ['status', '--porcelain', '--', '.', self::EXCLUDE_AGENTS_PATHSPEC]);
        if (trim($status->output()) === '') {
            return [];
        }

        $this->git($worktreePath, ['add', '-A', '--', '.', self::EXCLUDE_AGENTS_PATHSPEC]);
        $commit = $this->git($worktreePath, ['commit', '-m', "Mimari Doktor otomatik düzeltme (run #{$fixRunId})"]);
        if (! $commit->successful()) {
            throw new RuntimeException("git commit başarısız: {$commit->errorOutput()}");
        }

        $diff = $this->git($worktreePath, ['diff', '--name-only', 'HEAD~1']);

        return array_values(array_filter(explode("\n", trim($diff->output()))));
    }

    /**
     * `.env`, `vendor`, `node_modules` gitignored (ve tracked skeleton'ı
     * olmadığı) için worktree içinde `php artisan` çalışabilmesi adına canlı
     * checkout'tan symlink'lenir. `.claude/agents` ayrıca ele alınır: `.claude/`
     * dizini bazı tracked dosyalar (örn. settings.local.json) yüzünden worktree
     * checkout'unda zaten var olabilir, ama `.claude/agents/` henüz
     * commit'lenmemişse worktree'ye dahil olmaz — bu yüzden alt dizin ayrı
     * symlink'lenir.
     */
    private function linkSharedPaths(string $basePath, string $worktreePath): void
    {
        foreach (self::SHARED_LINKS as $item) {
            $source = "{$basePath}/{$item}";
            $target = "{$worktreePath}/{$item}";

            if (File::exists($source) && ! File::exists($target) && ! is_link($target)) {
                symlink($source, $target);
            }
        }

        $agentsSource = "{$basePath}/.claude/agents";
        $agentsTarget = "{$worktreePath}/.claude/agents";
        if (File::exists($agentsSource) && ! File::exists($agentsTarget) && ! is_link($agentsTarget)) {
            File::ensureDirectoryExists("{$worktreePath}/.claude");
            symlink($agentsSource, $agentsTarget);
        }
    }

    /**
     * @return array{generated_at: ?string, total: int, passed: int, failed: int, category_summaries: array}
     */
    private function captureDoctorSummary(string $worktreePath): array
    {
        $result = Process::path($worktreePath)->timeout(120)->run([PHP_BINARY, 'artisan', 'architecture:doctor', '--json']);
        if (! $result->successful()) {
            throw new RuntimeException("architecture:doctor çalıştırılamadı: {$result->errorOutput()}");
        }

        $jsonPath = "{$worktreePath}/storage/app/architecture-doctor.json";
        $report = File::exists($jsonPath) ? json_decode(File::get($jsonPath), true) : [];
        $results = $report['results'] ?? [];
        $passed = count(array_filter($results, fn (array $r) => $r['passed']));

        return [
            'generated_at' => $report['generated_at'] ?? null,
            'total' => count($results),
            'passed' => $passed,
            'failed' => count($results) - $passed,
            'category_summaries' => $report['category_summaries'] ?? [],
        ];
    }

    private function git(string $cwd, array $args)
    {
        return Process::path($cwd)->timeout(120)->run(['git', ...$args]);
    }
}
