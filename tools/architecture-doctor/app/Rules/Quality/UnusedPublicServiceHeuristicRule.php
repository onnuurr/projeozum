<?php

namespace ArchitectureDoctor\Rules\Quality;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use ArchitectureDoctor\Contracts\Lifecycle;
use ArchitectureDoctor\Contracts\Maturity;
use ArchitectureDoctor\Contracts\Severity;
use ArchitectureDoctor\Report\Finding;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Modules/*Services/*.php altındaki her public sınıf için kısa adının dosyanın
 * dışında kaç kez geçtiğini sayan basit bir referans-sayımı (grep tabanlı). 0-1 geçiş
 * "kullanılmıyor olabilir" Finding'i üretir.
 *
 * Açıkça heuristik: string tabanlı app()->make('X'), config-driven binding, route-model
 * binding gibi dinamik çözümlemeleri KAÇIRIR — bu yüzden sadece rapor, asla blocking
 * değil, asla Maturity::Stable'a yükselmez (bkz. plan Faz 6).
 */
final class UnusedPublicServiceHeuristicRule implements ArchitectureRule
{
    private const REFERENCE_THRESHOLD = 1;

    /**
     * @param  string[]|null  $servicePaths  Modules/*Services dizinleri (sadece üst seviye dosyalar taranır)
     * @param  string[]|null  $searchPaths  referans sayımı için taranacak kod tabanı
     */
    public function __construct(
        private readonly ?array $servicePaths = null,
        private readonly ?array $searchPaths = null,
    ) {}

    public function id(): string
    {
        return 'quality.unused-public-service';
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
        $serviceFiles = $this->serviceFiles();

        if ($serviceFiles === []) {
            return [];
        }

        $corpus = $this->buildCorpus(
            array_values(array_filter($this->searchPaths ?? $this->defaultSearchPaths(), 'is_dir')),
        );

        $findings = [];

        foreach ($serviceFiles as $file) {
            $className = $file->getFilenameWithoutExtension();

            if (! preg_match('/\bclass\s+'.preg_quote($className, '/').'\b/', $file->getContents())) {
                continue;
            }

            $references = $this->countReferences($className, $file->getPathname(), $corpus);

            if ($references > self::REFERENCE_THRESHOLD) {
                continue;
            }

            $findings[] = new Finding(
                message: "{$className} dosya dışında sadece {$references} kez geçiyor — kullanılmıyor olabilir.",
                file: $this->relativePath($file->getPathname()),
                suggestion: "Bu bir heuristik: string tabanlı app()->make(), config-driven binding ya da route-model binding gibi dinamik çözümlemeleri kaçırabilir. Elle doğrulayın; gerçekten ölüyse silin.",
            );
        }

        return $findings;
    }

    /**
     * @return SplFileInfo[]
     */
    private function serviceFiles(): array
    {
        $paths = array_values(array_filter($this->servicePaths ?? $this->defaultServicePaths(), 'is_dir'));

        if ($paths === []) {
            return [];
        }

        return iterator_to_array(
            Finder::create()->files()->name('*.php')->depth(0)->in($paths)->sortByName(),
            false,
        );
    }

    /**
     * @param  string[]  $searchPaths
     * @return array<string, string> dosya yolu => içerik
     */
    private function buildCorpus(array $searchPaths): array
    {
        if ($searchPaths === []) {
            return [];
        }

        $corpus = [];

        foreach (Finder::create()->files()->name('*.php')->in($searchPaths) as $file) {
            $corpus[$file->getPathname()] = $file->getContents();
        }

        return $corpus;
    }

    /**
     * @param  array<string, string>  $corpus
     */
    private function countReferences(string $className, string $ownFile, array $corpus): int
    {
        $pattern = '/\b'.preg_quote($className, '/').'\b/';
        $count = 0;

        foreach ($corpus as $path => $content) {
            if ($path === $ownFile) {
                continue;
            }

            $count += preg_match_all($pattern, $content);
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private function defaultServicePaths(): array
    {
        return glob(base_path('Modules/*/Services'), GLOB_ONLYDIR) ?: [];
    }

    /**
     * @return string[]
     */
    private function defaultSearchPaths(): array
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
