<?php

namespace ArchitectureDoctor\Engine;

use ArchitectureDoctor\Contracts\ArchitectureRule;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Keşif iki ayrı sorumluluğa bölünür: tarama kapsamı (bu sınıf, performans meselesi —
 * hangi dosyalar aday) ve üyelik kararı (ReflectionClass::implementsInterface, mimari
 * meselesi — bu sınıf gerçekten bir Rule mi). Dosya adı deseni (ör. "*Rule.php")
 * üyelik kararında hiç kullanılmaz.
 */
final class RuleDiscovery
{
    public function __construct(
        private readonly string $rulesPath = __DIR__.'/../Rules',
        private readonly string $rulesNamespace = 'ArchitectureDoctor\\Rules',
    ) {}

    /**
     * @return array<class-string<ArchitectureRule>>
     */
    public function discover(): array
    {
        if (! is_dir($this->rulesPath)) {
            return [];
        }

        $classes = [];

        foreach (Finder::create()->files()->name('*.php')->in($this->rulesPath) as $file) {
            $relativeClass = Str::of($file->getRelativePathname())
                ->replace(['/', '\\'], '\\')
                ->beforeLast('.php');

            $fqcn = $this->rulesNamespace.'\\'.$relativeClass;

            if (! class_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);

            if ($reflection->isInstantiable() && $reflection->implementsInterface(ArchitectureRule::class)) {
                $classes[] = $fqcn;
            }
        }

        return $classes;
    }
}
