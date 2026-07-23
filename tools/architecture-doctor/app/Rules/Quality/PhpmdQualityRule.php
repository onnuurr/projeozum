<?php

namespace ArchitectureDoctor\Rules\Quality;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;
use Symfony\Component\Process\Process;

/**
 * "unused class/dead controller" gibi proje-geneli erişilebilirlik analizini sıfırdan
 * yazmak yerine olgun bir araç sarmalanıyor — ModuleBoundaryRule'un deptrac'ı
 * sarmalamasıyla aynı desen. phpmd'nin codesize + unusedcode ruleset'leri: uzun metot,
 * god class/large class, yüksek cyclomatic complexity, kullanılmayan private
 * method/parametre/local variable burayı kapsar.
 *
 * Bu kategori (Faz 6, doctor.md'nin kendi ifadesiyle) hiçbir zaman bloklamaz — kalıcı
 * olarak rapor-only tasarlandı, asla Maturity::Stable'a yükseltilmez.
 */
final class PhpmdQualityRule implements ArchitectureRule
{
    private const RULESETS = 'codesize,unusedcode';

    /**
     * @param  string[]|null  $paths
     */
    public function __construct(private readonly ?array $paths = null) {}

    public function id(): string
    {
        return 'quality.phpmd';
    }

    public function category(): string
    {
        return 'Quality';
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
        $paths = array_values(array_filter($this->paths ?? $this->defaultPaths(), 'is_dir'));

        if ($paths === []) {
            return [];
        }

        $process = new Process(
            [
                base_path('vendor/bin/phpmd'),
                implode(',', $paths),
                'xml',
                self::RULESETS,
                '--exclude',
                'vendor,node_modules,storage',
            ],
            base_path(),
            timeout: 180,
        );

        $process->run();

        $xml = simplexml_load_string($process->getOutput());

        if ($xml === false) {
            return [new Finding(
                message: 'phpmd çalıştırılamadı veya beklenmeyen çıktı üretti.',
                suggestion: trim($process->getErrorOutput()) ?: 'vendor/bin/phpmd komutunu elle çalıştırıp hatayı inceleyin.',
            )];
        }

        $findings = [];

        foreach ($xml->file as $file) {
            $path = (string) $file['name'];

            foreach ($file->violation as $violation) {
                $externalInfoUrl = (string) $violation['externalInfoUrl'];

                $findings[] = new Finding(
                    message: trim((string) $violation).' ('.(string) $violation['rule'].')',
                    file: $this->relativePath($path),
                    line: (int) $violation['beginline'],
                    suggestion: $externalInfoUrl !== '' ? $externalInfoUrl : null,
                );
            }
        }

        return $findings;
    }

    /**
     * @return string[]
     */
    private function defaultPaths(): array
    {
        return array_values(array_filter(
            [app_path(), base_path('Modules')],
            'is_dir',
        ));
    }

    private function relativePath(string $absolutePath): string
    {
        $basePath = rtrim(base_path(), '/');

        return ltrim(str_replace($basePath, '', $absolutePath), '/');
    }
}
