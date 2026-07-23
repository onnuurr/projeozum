<?php

namespace ArchitectureDoctor\Rules\Migrations;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;
use Symfony\Component\Process\Process;

/**
 * CLAUDE.md "Prod'a gitmiş migration'ı düzenleme" maddesi için fuzzy bir sinyal: introducedIn
 * sonrası oluşturulmuş bir migration dosyası ilk eklendikten sonra tekrar commit edilmişse
 * (git log --follow > 1 kayıt) işaretler. Bu repo'da migration'lar tek-commit disiplinine
 * sahip değil (agent doğruladı), bu yüzden Warning ve kalıcı olarak Experimental kalması
 * bekleniyor — asla CI'ı bloklamaz.
 */
final class MigrationNotEditedAfterMergeRule implements ArchitectureRule
{
    /**
     * @param  string[]|null  $paths
     */
    public function __construct(
        private readonly ?array $paths = null,
        private readonly ?string $repoPath = null,
    ) {}

    public function id(): string
    {
        return 'migration.not-edited-after-merge';
    }

    public function category(): string
    {
        return 'Migrations';
    }

    public function severity(): Severity
    {
        return Severity::Warning;
    }

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle(Maturity::Experimental, '2026-07-23');
    }

    public function check(): array
    {
        $introducedIn = $this->lifecycle()->introducedIn;
        $repoPath = $this->repoPath ?? base_path();
        $findings = [];

        foreach (MigrationFileScanner::files($this->paths) as $file) {
            $date = MigrationFileScanner::dateFromFilename($file->getFilename());

            if ($date === null || $date <= $introducedIn) {
                continue;
            }

            $commitCount = $this->commitCount($repoPath, $file->getPathname());

            if ($commitCount === null || $commitCount <= 1) {
                continue;
            }

            $findings[] = new Finding(
                message: "Migration ilk eklendikten sonra tekrar commit edilmiş ({$commitCount} kayıt) — prod'a gitmiş bir migration sonradan düzenlenmiş olabilir.",
                file: MigrationFileScanner::relativePath($file->getPathname(), $repoPath),
                suggestion: "Migration'ı değiştirmek yerine üstüne yeni bir migration ekleyin (bkz. CLAUDE.md 'Prod'a gitmiş migration'ı düzenleme').",
            );
        }

        return $findings;
    }

    private function commitCount(string $repoPath, string $absoluteFilePath): ?int
    {
        $relative = MigrationFileScanner::relativePath($absoluteFilePath, $repoPath);

        $process = new Process(
            ['git', 'log', '--follow', '--oneline', '--', $relative],
            $repoPath,
            timeout: 15,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $lines = array_filter(explode("\n", trim($process->getOutput())), fn (string $l): bool => $l !== '');

        return count($lines);
    }
}
