<?php

namespace ArchitectureDoctor\Rules\Controllers;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Controller Rule'ları arasında paylaşılan tarama mantığı — MigrationFileScanner /
 * AiServiceProviderScanner ile aynı desen. ArchitectureRule'u implement etmediği için
 * RuleDiscovery bunu bir Rule olarak keşfetmez.
 */
final class ControllerFileScanner
{
    /**
     * @return string[]
     */
    public static function defaultPaths(): array
    {
        return array_values(array_filter(
            array_merge(
                [app_path('Http/Controllers')],
                glob(base_path('Modules/*/Http/Controllers'), GLOB_ONLYDIR) ?: [],
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
        $paths = array_values(array_filter($paths ?? self::defaultPaths(), 'is_dir'));

        if ($paths === []) {
            return [];
        }

        return iterator_to_array(
            Finder::create()->files()->name('*.php')->in($paths)->sortByName(),
            false,
        );
    }

    public static function relativePath(string $absolutePath, ?string $basePath = null): string
    {
        $basePath = rtrim($basePath ?? base_path(), '/');

        return ltrim(str_replace($basePath, '', $absolutePath), '/');
    }
}
