<?php

namespace ArchitectureDoctor\Rules\Controllers;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;

/**
 * laravel-controller skill'inin "controller iş kuralı taşımaz, Service'e delege eder"
 * ilkesinin en net, en az tartışmalı ihlalini denetler: ham `DB::table(` sorgusu.
 * Skill'in bilerek meşru saydığı çoklu-tablo `DB::transaction` ve tekli Eloquent
 * create/update controller içi kullanımları BU KURALIN KAPSAMI DIŞINDA — sadece query
 * builder'a inmiş, hiçbir modelden geçmeyen çağrılar hedefleniyor (bkz. plan Faz 4
 * kapsam daraltması). Mevcut kod tabanında 0 ihlal — hızlı Stable adayı.
 */
final class ControllerBypassesDbTableRule implements ArchitectureRule
{
    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'controller.db-table-bypass';
    }

    public function category(): string
    {
        return 'Controllers';
    }

    public function severity(): Severity
    {
        return Severity::Critical;
    }

    public function lifecycle(): Lifecycle
    {
        return new Lifecycle(Maturity::Experimental, '2026-07-23');
    }

    public function check(): array
    {
        $findings = [];

        foreach (ControllerFileScanner::files($this->paths) as $file) {
            if (! str_contains($file->getContents(), 'DB::table(')) {
                continue;
            }

            $findings[] = new Finding(
                message: "{$file->getFilenameWithoutExtension()} DB::table( ile ham query builder'a iniyor — controller iş kuralı/sorgu taşımamalı.",
                file: ControllerFileScanner::relativePath($file->getPathname()),
                suggestion: 'Sorguyu bir Eloquent model kapsamına ya da ilgili Service sınıfına taşıyın (bkz. laravel-controller/laravel-service skill).',
            );
        }

        return $findings;
    }
}
