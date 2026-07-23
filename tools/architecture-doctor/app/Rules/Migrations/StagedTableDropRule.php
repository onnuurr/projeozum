<?php

namespace ArchitectureDoctor\Rules\Migrations;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;

/**
 * CLAUDE.md "Tablo silme kademeli ve geri-dönülebilir olsun" maddesini denetler: introducedIn
 * tarihinden SONRAKİ migration'larda up() içinde drop edilen bir tablo, DAHA ÖNCEKİ bir
 * migration'da `_deprecated_` sonekiyle rename edilmemişse (karantinaya alınmamışsa) Finding
 * üretir. down() içindeki dropIfExists'ler (bir create-table migration'ının rollback'i)
 * kasıtlı olarak taranmaz — bu, up()'a özgü bir denetim.
 */
final class StagedTableDropRule implements ArchitectureRule
{
    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'migration.staged-table-drop';
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
        $quarantined = [];
        $findings = [];

        foreach (MigrationFileScanner::files($this->paths) as $file) {
            $upBody = MigrationFileScanner::methodBody($file->getContents(), 'up') ?? '';

            foreach ($this->renamedTables($upBody) as [, $to]) {
                if (str_contains($to, '_deprecated_')) {
                    $quarantined[$to] = true;
                }
            }

            $date = MigrationFileScanner::dateFromFilename($file->getFilename());

            if ($date === null || $date <= $introducedIn) {
                continue;
            }

            foreach ($this->droppedTables($upBody) as $table) {
                if (! ($quarantined[$table] ?? false)) {
                    $findings[] = new Finding(
                        message: "'{$table}' tablosu önce karantinaya alınmadan (rename ile _deprecated_ soneki) doğrudan drop ediliyor.",
                        file: MigrationFileScanner::relativePath($file->getPathname()),
                        suggestion: "Önce Schema::rename('{$table}', '{$table}_deprecated_<tarih>') ile karantinaya alın, birkaç sürüm sonra ayrı bir migration'la drop edin (bkz. CLAUDE.md).",
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function renamedTables(string $upBody): array
    {
        if (! preg_match_all(
            "/Schema::rename\\(\\s*['\"]([^'\"]+)['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/",
            $upBody,
            $matches,
            PREG_SET_ORDER,
        )) {
            return [];
        }

        return array_map(fn (array $m): array => [$m[1], $m[2]], $matches);
    }

    /**
     * @return string[]
     */
    private function droppedTables(string $upBody): array
    {
        if (! preg_match_all(
            "/Schema::(?:dropIfExists|drop)\\(\\s*['\"]([^'\"]+)['\"]\\s*\\)/",
            $upBody,
            $matches,
        )) {
            return [];
        }

        return $matches[1];
    }
}
