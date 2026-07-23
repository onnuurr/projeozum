<?php

namespace ArchitectureDoctor\Rules\Migrations;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Migration Rule'ları arasında paylaşılan tarama/parse mantığı — RuleDiscovery bu dizini
 * de tarar ama bu sınıf ArchitectureRule'u implement etmediği için bir Rule olarak
 * keşfedilmez (üyelik kararı isim değil interface'e göre, bkz. RuleDiscovery).
 */
final class MigrationFileScanner
{
    /**
     * @return string[]
     */
    public static function defaultPaths(): array
    {
        return array_values(array_filter(
            array_merge(
                [base_path('database/migrations')],
                glob(base_path('Modules/*/database/migrations'), GLOB_ONLYDIR) ?: [],
            ),
            'is_dir',
        ));
    }

    /**
     * @param  string[]|null  $paths
     * @return SplFileInfo[]
     */
    public static function files(?array $paths = null): array
    {
        $paths = $paths ?? self::defaultPaths();

        if ($paths === []) {
            return [];
        }

        return iterator_to_array(
            Finder::create()->files()->name('*.php')->in($paths)->sortByName(),
            false,
        );
    }

    /**
     * Migration dosya adı `YYYY_MM_DD_HHMMSS_...` ile başlar; bundan `Y-m-d` çıkarır.
     */
    public static function dateFromFilename(string $filename): ?string
    {
        if (! preg_match('/^(\d{4})_(\d{2})_(\d{2})_\d{6}_/', $filename, $m)) {
            return null;
        }

        return "{$m[1]}-{$m[2]}-{$m[3]}";
    }

    /**
     * Verilen metodun (up/down) gövdesini parantez dengesine göre çıkarır.
     */
    public static function methodBody(string $content, string $methodName): ?string
    {
        if (! preg_match(
            '/function\s+'.preg_quote($methodName, '/').'\s*\([^)]*\)\s*(?::\s*\??\s*\w+\s*)?\{/',
            $content,
            $match,
            PREG_OFFSET_CAPTURE,
        )) {
            return null;
        }

        $openBrace = $match[0][1] + strlen($match[0][0]) - 1;
        $depth = 0;

        for ($i = $openBrace, $len = strlen($content); $i < $len; $i++) {
            if ($content[$i] === '{') {
                $depth++;
            } elseif ($content[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $openBrace + 1, $i - $openBrace - 1);
                }
            }
        }

        return null;
    }

    /**
     * Bir proje dosya yolunu (base_path içinde) rapor için repo-göreli hale getirir.
     */
    public static function relativePath(string $absolutePath, ?string $basePath = null): string
    {
        $basePath = rtrim($basePath ?? base_path(), '/');

        return ltrim(str_replace($basePath, '', $absolutePath), '/');
    }
}
