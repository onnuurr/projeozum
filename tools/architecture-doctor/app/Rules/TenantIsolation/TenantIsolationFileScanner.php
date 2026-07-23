<?php

namespace ArchitectureDoctor\Rules\TenantIsolation;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * TenantScopedQueryRule için tarama yardımcısı — diğer kategorilerdeki
 * MigrationFileScanner/AiServiceProviderScanner/ControllerFileScanner ile aynı desen.
 * ArchitectureRule'u implement etmediği için RuleDiscovery bunu bir Rule olarak keşfetmez.
 */
final class TenantIsolationFileScanner
{
    private const MODULES = ['Product', 'Tenant', 'Atelier', 'Finance'];

    private const SUBDIRS = ['Http/Controllers', 'Services'];

    /**
     * @return string[]
     */
    public static function defaultPaths(): array
    {
        $paths = [];

        foreach (self::MODULES as $module) {
            foreach (self::SUBDIRS as $subdir) {
                $dir = base_path("Modules/{$module}/{$subdir}");

                if (is_dir($dir)) {
                    $paths[] = $dir;
                }
            }
        }

        return $paths;
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
