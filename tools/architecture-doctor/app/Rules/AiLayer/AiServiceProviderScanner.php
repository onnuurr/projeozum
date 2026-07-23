<?php

namespace ArchitectureDoctor\Rules\AiLayer;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * AI Layer Rule'ları (AiConcreteDriverBypassRule, AiMockDriverPresentRule) arasında
 * paylaşılan ServiceProvider bind() tarama/parse mantığı — MigrationFileScanner ile aynı
 * desen: ArchitectureRule'u implement etmediği için RuleDiscovery bunu bir Rule olarak
 * keşfetmez.
 */
final class AiServiceProviderScanner
{
    /**
     * @param  string[]|null  $paths
     * @return SplFileInfo[]
     */
    public static function providerFiles(?array $paths = null): array
    {
        $paths = array_values(array_filter($paths ?? [base_path('Modules')], 'is_dir'));

        if ($paths === []) {
            return [];
        }

        return iterator_to_array(
            Finder::create()->files()->name('*ServiceProvider.php')->in($paths)->sortByName(),
            false,
        );
    }

    /**
     * Bir ServiceProvider dosyasındaki her `$this->app->bind(XContract::class, ...)`
     * çağrısını, Contract'ın kısa adı + çağrının parantez-dengeli gövdesi + satır
     * numarasıyla birlikte döner.
     *
     * @return array<int, array{contract: string, body: string, line: int}>
     */
    public static function bindCalls(SplFileInfo $file): array
    {
        $content = $file->getContents();
        $calls = [];
        $searchOffset = 0;

        while (preg_match(
            '/\$this->app->bind\(\s*([A-Za-z_\\\\]+)::class\s*,/',
            $content,
            $m,
            PREG_OFFSET_CAPTURE,
            $searchOffset,
        )) {
            $callStart = $m[0][1];
            $parenStart = strpos($content, '(', $callStart);
            $body = self::balancedParens($content, $parenStart);

            if ($body === null) {
                break;
            }

            $calls[] = [
                'contract' => self::shortName($m[1][0]),
                'body' => $body,
                'line' => substr_count($content, "\n", 0, $callStart) + 1,
            ];

            $searchOffset = $parenStart + strlen($body) + 1;
        }

        return $calls;
    }

    /**
     * Bir bind() gövdesi içinde geçen `(Gemini|Fal|Mock)Xyz::class` referanslarının kısa
     * sınıf adlarını döner. Bu üç önek projede AI sürücüsü ismi olarak tutarlı kullanılıyor
     * (bkz. Faz 3 planı) — GeminiClient/FalClient gibi paylaşılan alt-katman sınıfları
     * hiçbir Contract'a bind edilmediği için bu tarama zaten onlara hiç değmez.
     *
     * @return string[]
     */
    public static function driverClassesIn(string $body): array
    {
        preg_match_all('/\b((?:Gemini|Fal|Mock)[A-Za-z0-9]*)::class/', $body, $m);

        return array_values(array_unique($m[1]));
    }

    public static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return (string) end($parts);
    }

    public static function relativePath(string $absolutePath, ?string $basePath = null): string
    {
        $basePath = rtrim($basePath ?? base_path(), '/');

        return ltrim(str_replace($basePath, '', $absolutePath), '/');
    }

    private static function balancedParens(string $content, int $openParenPos): ?string
    {
        $depth = 0;

        for ($i = $openParenPos, $len = strlen($content); $i < $len; $i++) {
            if ($content[$i] === '(') {
                $depth++;
            } elseif ($content[$i] === ')') {
                $depth--;

                if ($depth === 0) {
                    return substr($content, $openParenPos + 1, $i - $openParenPos - 1);
                }
            }
        }

        return null;
    }
}
